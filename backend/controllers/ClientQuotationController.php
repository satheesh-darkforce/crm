<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\components\LineItems;
use backend\models\ClientQuotation;
use backend\models\ClientQuotationItem;
use backend\models\ClientQuotationFile;
use backend\models\ClientQuotationSearch;
use backend\models\Developer;
use backend\models\PoDetail;
use backend\models\User;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\Fpdi;
use Yii;
use yii\db\Expression;
use yii\db\IntegrityException;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;

/**
 * Client Quotation controller — ported from delivery/controllers/QuotationController.php.
 *
 * Includes: CRUD, revision chain (child quotations), file attachment,
 * Add-Quote-By modal with developer official-contact flow, ajax Select2 sources,
 * preview + download-latest PDF with FPDI attachment merge.
 *
 * Simplified after DB merge:
 *   - `client_id` references `client.id` directly (no old_id lookup).
 *   - Single `db` connection (no dcdb).
 *   - Access gated by base Controller via UserAccess::checkAccess.
 */
class ClientQuotationController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => \yii\filters\VerbFilter::class, 'actions' => [
                'delete-file' => ['POST'], 'cancel' => ['POST'], 'complete-quote' => ['POST'],
                'save-developer-official-contact' => ['POST'], 'preview-pdf' => ['POST'],
                'set-status' => ['POST'], 'duplicate' => ['POST'],
            ]],
        ]);
    }

    /**
     * These helpers are in every developer's default routes (the quotation pages call them),
     * so they also need the matching quotation right: changes need create/update, reads need
     * any quotation access.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $any   = fn(array $r) => (bool)array_filter($r, fn($x) => \common\components\UserAccess::checkAccess($x));
        $write = ['add-po', 'complete-quote', 'delete-file', 'upload-file', 'save-developer-official-contact', 'set-status', 'duplicate'];
        $read  = ['download-file', 'download-po', 'preview-pdf', 'get-client-address', 'quote-by-users', 'additional-quote-by-users',
                  'developer-official-contact', 'current-developer-official-contact', 'get-next-no'];
        if (in_array($action->id, $write, true) && !$any(['client-quotation/create', 'client-quotation/update'])) {
            throw new \yii\web\ForbiddenHttpException('You do not have permission to change quotations.');
        }
        if (in_array($action->id, $read, true) && !$any(['client-quotation/index', 'client-quotation/view', 'client-quotation/create', 'client-quotation/update'])) {
            throw new \yii\web\ForbiddenHttpException('You do not have permission to view quotations.');
        }
        return true;
    }

    /** Full pages get the Delivery / PO / Quotation module assets. */
    public function render($view, $params = []): string
    {
        \backend\assets\DeliveryAsset::register($this->getView());
        return parent::render($view, $params);
    }
    // ─────────────────────────────────────────────────────────────── CRUD ──

    public function actionIndex(): string
    {
        $searchModel  = new ClientQuotationSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $clientOptions = ArrayHelper::map(
            ClientModel::find()->where(['status' => 1])->orderBy('client_name')->asArray()->all(),
            'id', 'client_name'
        );
        $companyNames = ClientModel::find()
            ->select('company_name')->distinct()
            ->where(['status' => 1])
            ->andWhere(['not', ['company_name' => null]])
            ->andWhere(['<>', 'company_name', ''])
            ->orderBy('company_name')
            ->column();
        $companyOptions = array_combine($companyNames, $companyNames);
        $total          = (int) ClientQuotation::find()->where(['!=', 'status', 0])->count();
        $cancelledTotal = (int) ClientQuotation::find()->where(['status' => 2])->count();
        $completedTotal = (int) ClientQuotation::find()->where(['status' => 1, 'completed_status' => 1])->count();

        return $this->render('index', [
            'searchModel'    => $searchModel,
            'dataProvider'   => $dataProvider,
            'clientOptions'  => $clientOptions,
            'companyOptions' => $companyOptions,
            'userOptions'    => $this->getActiveQuoteByUserOptions(),
            'total'          => $total,
            'cancelledTotal' => $cancelledTotal,
            'completedTotal' => $completedTotal,
        ]);
    }

    public function actionCreate(): string|Response|array
    {
        $model     = new ClientQuotation();
        $fileModel = new ClientQuotationFile();

        $nextQuoteSequence = $this->getNextQuoteSequenceNumber();
        if ($clientId = (int) Yii::$app->request->get('id', 0)) {
            $model->client_id = $clientId;
        }
        // Defaults for a new quote: Green Divine's terms, 15 days validity, GST type from the client.
        $model->terms       = ClientQuotation::DEFAULT_TERMS;
        $model->notes       = ClientQuotation::DEFAULT_NOTES;
        $model->valid_until = date('Y-m-d', strtotime('+15 days'));
        $model->tax_mode    = LineItems::taxModeFor($model->client_id ? ClientModel::findOne($model->client_id) : null);
        if (\backend\components\Crm::simple()) {
            // Green Divine mode: one quote series, no Deployment/Trial choice — number shown straight away.
            $model->quote_for       = 1;
            $model->deployment_type = ClientQuotation::DEPLOYMENT;
            $model->quote_no        = $this->buildTentativeQuoteNumber(1, $nextQuoteSequence);
        }
        $lines = [];
        $lineErrors = [];

        // Ajax validation pass for ActiveForm.
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $model->quote_date  = $model->quote_date ?: date('Y-m-d');
            $model->created_at  = $now;
            $model->modified_at = $now;
            $model->status      = 1;
            $model->quoted_by   = (int) Yii::$app->user->id;
            $model->parent_id   = null;
            $model->quote_no    = $this->buildTentativeQuoteNumber((int) $model->quote_for, $nextQuoteSequence);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $model->quote_date  = $model->quote_date ?: date('Y-m-d');
            $model->created_at  = $now;
            $model->modified_at = $now;
            $model->status      = 1;
            $model->quoted_by   = (int) Yii::$app->user->id;
            $model->parent_id   = null;

            [$lines, $totals, $lineErrors] = $this->readQuoteItems($model);
            if (empty($model->client_id)) {
                Yii::$app->session->setFlash('error', 'Selected client is not available. Please choose a valid client.');
            } elseif ($lineErrors) {
                Yii::$app->session->setFlash('error', 'Please check the items — ' . $lineErrors[0]);
            } elseif ($this->saveQuoteWithItems($model, $lines, $totals, fn() => $this->saveQuotationWithGeneratedNumber($model))) {
                if ($file = UploadedFile::getInstance($fileModel, 'file_path')) {
                    $this->saveQuotationFile($model, $file);
                }
                Yii::$app->session->setFlash('success', 'Quotation created successfully');
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                $errors = $model->getFirstErrors();
                Yii::$app->session->setFlash('error', reset($errors) ?: 'Unable to create quotation');
            }
        }

        return $this->render('create', [
            'model'         => $model,
            'fileModel'     => $fileModel,
            'clientOptions' => $this->clientSelectOptions(),
            'userOptions'   => $this->getAdditionalQuoteBySelectedOptions((int) $model->alternate_quote_by),
            'quotenum'      => $nextQuoteSequence,
            'lines'         => $lines,
            'lineErrors'    => $lineErrors,
        ]);
    }

    public function actionUpdate(int $id): string|Response|array
    {
        $model     = $this->findModel($id);
        $fileModel = new ClientQuotationFile();
        if (!empty($model->quote_date)) {
            $model->quote_date = date('Y-m-d', strtotime($model->quote_date));
        }
        if ((int)$model->quote_status === ClientQuotation::QS_INVOICED) {
            Yii::$app->session->setFlash('error', 'This quotation has been invoiced and can no longer be edited. Duplicate it to make a new one.');
            return $this->redirect(['view', 'id' => $id]);
        }
        if (!$model->tax_mode) {
            $model->tax_mode = LineItems::taxModeFor($model->client);
        }
        $lines = array_map(fn(ClientQuotationItem $i) => $i->toLine(), $model->items);
        $lineErrors = [];

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            $model->modified_at = date('Y-m-d H:i:s');
            $model->quoted_by   = (int) Yii::$app->user->id;
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->modified_at = date('Y-m-d H:i:s');
            $model->quoted_by   = (int) Yii::$app->user->id;

            [$lines, $totals, $lineErrors] = $this->readQuoteItems($model);
            if ($lineErrors) {
                Yii::$app->session->setFlash('error', 'Please check the items — ' . $lineErrors[0]);
            } elseif ($this->saveQuoteWithItems($model, $lines, $totals, fn() => $model->save())) {
                if ($file = UploadedFile::getInstance($fileModel, 'file_path')) {
                    $this->saveQuotationFile($model, $file);
                }
                Yii::$app->session->setFlash('success', 'Quotation updated successfully');
                return $this->redirect(['view', 'id' => $model->id]);
            }
            $errors = $model->getFirstErrors();
            Yii::$app->session->setFlash('error', reset($errors) ?: 'Unable to update quotation');
        }

        return $this->render('update', [
            'model'         => $model,
            'fileModel'     => $fileModel,
            'clientOptions' => $this->clientSelectOptions(),
            'userOptions'   => $this->getAdditionalQuoteBySelectedOptions((int) $model->alternate_quote_by),
            'lines'         => $lines,
            'lineErrors'    => $lineErrors,
        ]);
    }

    public function actionView(int $id): string
    {
        $model          = $this->findModel($id);
        $childModel     = new ClientQuotation();
        $childFileModel = new ClientQuotationFile();

        $rootId = $model->parent_id ?: $model->id;
        $relatedQuotes = ClientQuotation::find()
            ->where(['or', ['id' => $rootId], ['parent_id' => $rootId]])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $rootQuote = $rootId === $model->id ? $model : $this->findModel($rootId);
        // Submitter info on the view page always reflects the parent quotation,
        // even when looking at a revision (child).
        $user             = $rootQuote->quoted_by ? User::findOne(['user_id' => $rootQuote->quoted_by]) : null;
        $additionalUser   = $rootQuote->alternate_quote_by
            ? User::findOne(['user_id' => $rootQuote->alternate_quote_by]) : null;
        $childModel->quote_no = $this->generateNextAvailableChildQuoteNumber($rootQuote);

        // Purchase Orders attached to this quotation tree (parent-keyed).
        $poList = PoDetail::find()
            ->where(['quote_id' => $rootId, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        return $this->render('view', [
            'model'          => $model,
            'childModel'     => $childModel,
            'childFileModel' => $childFileModel,
            'relatedQuotes'  => $relatedQuotes,
            'user'           => $user,
            'additionalUser' => $additionalUser,
            'rootQuote'      => $rootQuote,
            'poList'         => $poList,
        ]);
    }

    /** Add a revision (child) quotation under a root. */
    public function actionAddChild(int $id): string|Response|array
    {
        $parent         = $this->findModel($id);
        $child          = new ClientQuotation();
        $childFileModel = new ClientQuotationFile();

        if (Yii::$app->request->isAjax && $child->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $child->client_id   = $parent->client_id;
            $child->address     = $parent->address;
            $child->parent_id   = $parent->parent_id ?: $parent->id;
            $child->quote_date  = date('Y-m-d');
            $child->created_at  = $now;
            $child->modified_at = $now;
            $child->status      = 1;
            $child->quoted_by   = (int) Yii::$app->user->id;
            if (empty($child->project_name)) {
                $child->project_name = $parent->project_name;
            }
            $rootQuote = $child->parent_id ? $this->findModel($child->parent_id) : $parent;
            // A revision always carries the root's deployment type (Deployment /
            // Trial) — never editable on a child. This also drives the cost
            // optional/required rule so a Trial revision can omit the cost.
            $child->deployment_type = (int) $rootQuote->deployment_type;
            $child->quote_no = $this->generateNextAvailableChildQuoteNumber($rootQuote);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($child);
        }

        if (Yii::$app->request->isPost && $child->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $child->client_id   = $parent->client_id;
            $child->address     = $parent->address;
            $child->parent_id   = $parent->parent_id ?: $parent->id;
            $child->quote_date  = date('Y-m-d');
            $child->quote_for   = $parent->quote_for;
            $child->created_at  = $now;
            $child->modified_at = $now;
            $child->status      = 1;
            $child->quoted_by   = (int) Yii::$app->user->id;
            if (empty($child->project_name)) {
                $child->project_name = $parent->project_name;
            }
            $rootQuote = $child->parent_id ? $this->findModel($child->parent_id) : $parent;
            // Inherit the root's deployment type (Deployment / Trial) — a
            // revision is always the same type as its parent, and a Trial
            // revision may omit the estimation cost.
            $child->deployment_type = (int) $rootQuote->deployment_type;

            $file = UploadedFile::getInstance($childFileModel, 'file_path');
            if (!$file) {
                Yii::$app->session->setFlash('error', 'Attach File is required for a revision.');
                return $this->redirect(['view', 'id' => $parent->id]);
            }
            if (strtolower((string) $file->extension) !== 'pdf') {
                Yii::$app->session->setFlash('error', 'Attach File must be a PDF.');
                return $this->redirect(['view', 'id' => $parent->id]);
            }

            if ($this->saveQuotationWithGeneratedNumber($child, $rootQuote)) {
                $this->saveQuotationFile($child, $file);
                Yii::$app->session->setFlash('success', 'Additional quotation added successfully');
                return $this->redirect(['view', 'id' => $parent->id]);
            }
            $errors = $child->getFirstErrors();
            Yii::$app->session->setFlash('error', reset($errors) ?: 'Unable to add quotation');
        }

        return $this->redirect(['view', 'id' => $parent->id]);
    }

    /** Quotation files listing. */
    public function actionFiles(int $id): string
    {
        $model = $this->findModel($id);
        $files = ClientQuotationFile::find()
            ->where(['quote_id' => $model->id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        return $this->render('quotationfile', ['model' => $model, 'files' => $files]);
    }

    public function actionDownloadFile(int $id): Response
    {
        $file = ClientQuotationFile::findOne($id);
        if (!$file || (int)$file->status === 0) {
            throw new NotFoundHttpException('File not found.');
        }
        $path = Yii::getAlias('@webroot/') . ltrim($file->file_path, '/');
        if (!is_file($path)) {
            throw new NotFoundHttpException('File missing on disk.');
        }
        // Open in the browser tab (PDFs/images preview, others download).
        // The view links the anchor with target="_blank" so the user lands here.
        return Yii::$app->response->sendFile($path, basename($path), ['inline' => true]);
    }

    public function actionDeleteFile(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $file = ClientQuotationFile::findOne(['id' => $id, 'status' => 1]);
        if (!$file) {
            return ['success' => false, 'message' => 'That file was already removed.'];
        }
        $file->status = 0;
        return $file->save(false)
            ? ['success' => true]
            : ['success' => false, 'message' => 'Could not delete file.'];
    }

    // ──────────────────────────────────────────────────── cancel / address ──

    public function actionCancel(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id     = (int) Yii::$app->request->post('id');
        $reason = trim((string) Yii::$app->request->post('reason', ''));
        if ($id <= 0)          return ['success' => false, 'message' => 'Invalid quotation selected'];
        if ($reason === '')    return ['success' => false, 'message' => 'Reason should not be empty'];

        $m = ClientQuotation::findOne($id);
        if (!$m)                          return ['success' => false, 'message' => 'Quotation not found'];
        if ((int)$m->status === 2)        return ['success' => false, 'message' => 'Already cancelled.'];

        $m->status        = 2;
        $m->cancel_reason = $reason;
        if (!$m->save(false)) {
            return ['success' => false, 'message' => $this->formatModelErrors($m)];
        }
        return ['success' => true, 'message' => 'Quotation cancelled successfully.', 'id' => $id, 'reason' => $reason];
    }

    public function actionGetClientAddress(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int) Yii::$app->request->post('id');
        $c  = ClientModel::findOne($id);
        return ['address' => $c->address ?? ''];
    }

    // ─────────────────────────────────────────── Quote-By Select2 sources ──

    public function actionQuoteByUsers($term = null): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['results' => $this->searchPopupQuoteByUsers((string) $term)];
    }

    public function actionAdditionalQuoteByUsers($term = null): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['results' => $this->searchQuoteByUsers((string) $term, true)];
    }

    public function actionDeveloperOfficialContact(int $userId): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if ($userId <= 0) return ['success' => false, 'message' => 'Invalid user selected.'];

        $user = $this->findPopupQuoteByUser($userId);
        if (!$user) return ['success' => false, 'message' => 'User not found.'];

        $developer = $this->findDeveloperByUserId($userId);

        return [
            'success' => true,
            'user' => [
                'id'   => (int) $user->user_id,
                'text' => $this->formatPopupQuoteByUserLabel($user),
            ],
            'developer' => [
                'official_email_id'  => trim((string)($developer->official_email_id  ?? '')),
                'official_mobile_no' => trim((string)($developer->official_mobile_no ?? '')),
            ],
        ];
    }

    public function actionCurrentDeveloperOfficialContact(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $userId = (int) Yii::$app->user->id;

        $user = Developer::find()
            ->select(['user_id', 'developer_name', 'official_email_id', 'official_mobile_no', 'status'])
            ->where(['user_id' => $userId, 'status' => 1])
            ->one();

        if (!$user) return ['success' => false, 'message' => 'Current developer record not found.'];

        return [
            'success' => true,
            'user' => [
                'id'   => (int) $user->user_id,
                'text' => $this->formatPopupQuoteByUserLabel($user),
            ],
            'developer' => [
                'official_email_id'  => trim((string) ($user->official_email_id  ?? '')),
                'official_mobile_no' => trim((string) ($user->official_mobile_no ?? '')),
            ],
            'requires_update' => trim((string) ($user->official_email_id  ?? '')) === ''
                              || trim((string) ($user->official_mobile_no ?? '')) === '',
        ];
    }

    public function actionSaveDeveloperOfficialContact(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $userId            = (int) Yii::$app->request->post('user_id');
        $allowCurrentUser  = (int) Yii::$app->request->post('allow_current_user', 0) === 1;
        $user              = $allowCurrentUser
            ? $this->findCurrentOrPopupQuoteByUser($userId)
            : $this->findPopupQuoteByUser($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Please select a valid user other than the current user.',
                'errors'  => ['user_id' => 'Please select a valid user other than the current user.'],
            ];
        }

        $developer = $this->findDeveloperByUserId($userId);
        if (!$developer) {
            return [
                'success' => false,
                'message' => 'Developer record not found for the selected user.',
                'errors'  => ['user_id' => 'Developer record not found for the selected user.'],
            ];
        }

        $developer->official_email_id  = trim((string) Yii::$app->request->post('official_email_id',  ''));
        $developer->official_mobile_no = trim((string) Yii::$app->request->post('official_mobile_no', ''));

        if ($developer->official_email_id  === '') $developer->addError('official_email_id',  'Official email ID cannot be blank.');
        if ($developer->official_mobile_no === '') $developer->addError('official_mobile_no', 'Official phone number cannot be blank.');

        if ($developer->hasErrors() || !$developer->validate(['official_email_id', 'official_mobile_no'])) {
            $errs = [
                'official_email_id'  => $developer->getFirstError('official_email_id'),
                'official_mobile_no' => $developer->getFirstError('official_mobile_no'),
            ];
            return ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => array_filter($errs)];
        }

        if (!$developer->save(false, ['official_email_id', 'official_mobile_no'])) {
            return ['success' => false, 'message' => 'Unable to save official contact details right now.'];
        }

        return [
            'success' => true,
            'option' => [
                'id'   => (int) $user->user_id,
                'text' => $this->formatPopupQuoteByUserLabel($user),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────── PDF actions ──

    /** Inline preview (renders form-posted data) — used by the Preview button. */
    public function actionPreviewPdf(): string
    {
        $model     = new ClientQuotation();
        $fileModel = new ClientQuotationFile();

        if (!$model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->statusCode = 400;
            return 'Preview request is missing form data.';
        }

        // Display only — the preview never saves, so the guarded number is copied for the PDF.
        $model->quote_no = mb_substr((string) (Yii::$app->request->post('ClientQuotation')['quote_no'] ?? ''), 0, 50);
        $tempFiles = [];
        try {
            $file       = UploadedFile::getInstance($fileModel, 'file_path');
            $client     = ClientModel::find()->where(['id' => $model->client_id])->one();
            $quotedById = (int) ($model->quoted_by ?: Yii::$app->user->id);
            $developer  = $this->findDeveloperForQuotationUser($quotedById);
            $additional = $this->findDeveloperForQuotationUser((int) $model->alternate_quote_by);

            $html = $this->renderPartial('_pdf_template', [
                'preview'             => 1,
                'company_name'        => $client->company_name ?? '',
                'model'               => $model,
                'client'              => $client,
                'developer'           => $developer,
                'additionalDeveloper' => $additional,
            ]);

            $mpdf = $this->createQuotationPdf();
            $this->applyQuotationPdfBranding($mpdf);
            $mpdf->WriteHTML($html);

            $tempPdf = Yii::getAlias('@runtime') . '/form_' . uniqid() . '.pdf';
            $mpdf->Output($tempPdf, Destination::FILE);
            $tempFiles[] = $tempPdf;

            $fpdi = new Fpdi();
            $this->mergePdfIntoFpdi($fpdi, $tempPdf);
            if ($file) {
                $uploaded = Yii::getAlias('@runtime') . '/' . uniqid('qp_', true) . '.' . strtolower((string) $file->extension);
                $file->saveAs($uploaded);
                $tempFiles[] = $uploaded;
                if (strtolower((string) $file->extension) === 'pdf') {
                    // If the attachment is in an unsupported compression format and
                    // even Ghostscript can't flatten it, return the preview without
                    // the attachment rather than failing the whole preview.
                    $this->mergePdfIntoFpdi($fpdi, $uploaded);
                }
            }

            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'application/pdf');
            Yii::$app->response->headers->set('Content-Disposition', 'inline; filename="preview.pdf"');
            return $fpdi->Output('preview.pdf', 'S');
        } catch (\Throwable $e) {
            Yii::error('Preview PDF failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->statusCode = 500;
            return 'Unable to generate the preview right now. Please try again.';
        } finally {
            foreach ($tempFiles as $t) {
                @unlink($t);
            }
        }
    }

    /** Download the latest-revision PDF merged with its latest attachment. */
    public function actionDownloadLatest(int $id): string
    {
        $model  = $this->findModel($id);
        $rootId = $model->parent_id ?: $model->id;

        $quoteIds = ClientQuotation::find()
            ->select('id')
            ->where(['or', ['id' => $rootId], ['parent_id' => $rootId]])
            ->column();

        $latestQuote = ClientQuotation::find()
            ->where(['or', ['id' => $rootId], ['parent_id' => $rootId]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        $latestFile = ClientQuotationFile::find()
            ->where(['quote_id' => $quoteIds, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        // Submitted-By on the PDF inherits from the PARENT quotation — even
        // when the latest version is a revision submitted by someone else.
        $rootQuote  = $rootId === $model->id ? $model : $this->findModel($rootId);
        $developer  = $this->findDeveloperForQuotationUser((int) $rootQuote->quoted_by);
        $additional = $this->findDeveloperForQuotationUser((int) $rootQuote->alternate_quote_by);

        $html = $this->renderPartial('_pdf_template', [
            'model'               => $latestQuote,
            'developer'           => $developer,
            'additionalDeveloper' => $additional,
        ]);

        $mpdf = $this->createQuotationPdf();
        $this->applyQuotationPdfBranding($mpdf);
        $mpdf->WriteHTML($html);
        // Item-based quote: add the items page (lines, GST summary, bank, terms).
        if ($latestQuote && $latestQuote->hasItems()) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($this->renderPartial('_pdf_items', [
                'model' => $latestQuote,
                'bank'  => \backend\models\BankDetail::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'id' => SORT_ASC])->one(),
            ]));
        }

        $generated = Yii::getAlias('@runtime') . '/quotation_' . time() . '.pdf';
        $mpdf->Output($generated, Destination::FILE);

        $fpdi = new Fpdi();
        $this->mergePdfIntoFpdi($fpdi, $generated);
        if ($latestFile && !empty($latestFile->file_path)) {
            $path = Yii::getAlias('@webroot/') . ltrim($latestFile->file_path, '/');
            $this->mergePdfIntoFpdi($fpdi, $path);
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'application/pdf');
        Yii::$app->response->headers->add('Content-Disposition', 'attachment; filename="Quotation_' . $model->quote_no . '.pdf"');
        return $fpdi->Output('quotation.pdf', 'S');
    }

    /** Short alias for the View-page "PDF" button. */
    public function actionPdf(int $id): string
    {
        return $this->actionDownloadLatest($id);
    }

    public function actionGetNextNo(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $quoteFor = (int) Yii::$app->request->get('quote_for', 1);
        return [
            'success'  => true,
            'quote_no' => $this->buildTentativeQuoteNumber($quoteFor, $this->getNextQuoteSequenceNumber()),
        ];
    }

    // ─────────────────────────────────────────────── Purchase Orders (PO) ──

    /**
     * Add a Purchase Order against a quotation tree.
     * `id` is any quotation in the tree; the PO is keyed to the root quote_id.
     */
    public function actionAddPo(int $id): string|Response|array
    {
        $quote     = $this->findModel($id);
        $rootId    = $quote->parent_id ?: $quote->id;
        $rootQuote = $rootId === $quote->id ? $quote : $this->findModel($rootId);

        // Hard guards: cancelled or already-completed quotes cannot get new POs.
        if ((int) $rootQuote->status === 2) {
            Yii::$app->session->setFlash('error', 'Cannot add PO to a cancelled quotation.');
            return $this->redirect(['view', 'id' => $quote->id]);
        }
        if ((int) $rootQuote->completed_status === 1) {
            Yii::$app->session->setFlash('error', 'Cannot add PO — quotation is already marked as completed.');
            return $this->redirect(['view', 'id' => $quote->id]);
        }

        // Latest quotation under the parent (defines prefilled quote_no + given_cost).
        $latestQuote = ClientQuotation::find()
            ->where(['or', ['id' => $rootId], ['parent_id' => $rootId]])
            ->andWhere(['!=', 'status', 0])
            ->orderBy(['id' => SORT_DESC])
            ->one() ?: $rootQuote;

        $model = new PoDetail(['scenario' => 'create']);
        $model->quote_id   = $rootId;
        $model->date       = date('Y-m-d');
        $model->given_cost = (string) ($latestQuote->estimation_cost ?? '');
        $model->po_status  = 1; // 1=pending by default at creation

        // Active developers (usertype=2, active in both user + developer) for the PO In-charge dropdown.
        $inchargeOptions = User::find()
            ->alias('u')
            ->select(['d.developer_name', 'u.user_id'])
            ->innerJoin(['d' => Developer::tableName()], 'd.user_id = u.user_id')
            ->where(['u.status' => 1, 'u.usertype' => 2, 'd.status' => 1])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->indexBy('user_id')
            ->column();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            $model->po_file = UploadedFile::getInstance($model, 'po_file');
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->quote_id = $rootId; // lock to tree root regardless of posted value
            $model->po_file  = UploadedFile::getInstance($model, 'po_file');
            $now             = date('Y-m-d H:i:s');
            $model->created_at  = $now;
            $model->modified_at = $now;
            $model->status      = 1;

            // Strict Y-m-d; anything unreadable is left for the validator to reject.
            $toDt = function ($v) {
                $v = trim((string) $v);
                $d = \DateTime::createFromFormat('!Y-m-d', substr($v, 0, 10));
                return $v === '' ? null : ($d && $d->format('Y-m-d') === substr($v, 0, 10) ? $d->format('Y-m-d 00:00:00') : $v);
            };
            $model->date          = $toDt($model->date);
            $model->delivery_date = $toDt($model->delivery_date);
            if (empty($model->po_status)) {
                $model->po_status = 1;
            }
            // po_incharge column is NOT NULL on the live DB. Optional from the
            // user's perspective → store 0 (unassigned) when left blank.
            if ($model->po_incharge === '' || $model->po_incharge === null) {
                $model->po_incharge = 0;
            }

            if ($model->validate()) {
                $saved = $this->savePoFile($rootQuote, $model);
                if ($saved && $model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Purchase Order added successfully.');
                    $viewUrl = \yii\helpers\Url::to(['view', 'id' => $quote->id]);
                    // JS location.replace purges the Add PO URL from browser
                    // history — a server 302 leaves it behind, causing
                    // browser-back to bounce View ↔ AddPo.
                    $viewUrlJson = json_encode($viewUrl);
                    $response = Yii::$app->response;
                    $response->format = Response::FORMAT_RAW;
                    $response->headers->set('Content-Type', 'text/html; charset=utf-8');
                    $response->content = "<!doctype html><meta charset=\"utf-8\"><title>Redirecting…</title>"
                        . "<script>window.location.replace({$viewUrlJson});</script>"
                        . "<noscript><meta http-equiv=\"refresh\" content=\"0;url={$viewUrl}\">"
                        . "<a href=\"{$viewUrl}\">Continue</a></noscript>";
                    return $response;
                }
                Yii::$app->session->setFlash('error', 'Unable to save the PO file. Please try again.');
            } else {
                $errors = $model->getFirstErrors();
                Yii::$app->session->setFlash('error', reset($errors) ?: 'Please correct the highlighted fields.');
            }
        }

        return $this->render('add-po', [
            'model'           => $model,
            'rootQuote'       => $rootQuote,
            'latestQuote'     => $latestQuote,
            'returnId'        => $quote->id,
            'inchargeOptions' => $inchargeOptions,
        ]);
    }

    /** Mark the parent quotation tree as completed (completed_status=1). */
    public function actionCompleteQuote(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $id = (int) Yii::$app->request->post('id');
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid quotation selected.'];
        }

        $quote  = ClientQuotation::findOne($id);
        if (!$quote) {
            return ['success' => false, 'message' => 'Quotation not found.'];
        }

        $rootId = $quote->parent_id ?: $quote->id;
        $root   = ClientQuotation::findOne($rootId);
        if (!$root) {
            return ['success' => false, 'message' => 'Parent quotation not found.'];
        }
        if ((int) $root->status === 2) {
            return ['success' => false, 'message' => 'Cancelled quotations cannot be completed.'];
        }
        if ((int) $root->completed_status === 1) {
            return ['success' => false, 'message' => 'Quotation is already marked as completed.'];
        }

        // Cannot complete a quotation that has no POs raised yet.
        $poCount = (int) PoDetail::find()
            ->where(['quote_id' => $root->id, 'status' => 1])
            ->count();
        if ($poCount === 0) {
            return ['success' => false, 'message' => 'Add at least one Purchase Order before completing this quotation.'];
        }

        $root->completed_status = 1;
        $root->modified_at      = date('Y-m-d H:i:s');
        if (!$root->save(false, ['completed_status', 'modified_at'])) {
            return ['success' => false, 'message' => $this->formatModelErrors($root)];
        }

        return ['success' => true, 'message' => 'Quotation marked as completed.'];
    }

    /** Stream a PO file inline (preview/download). */
    public function actionDownloadPo(int $id): Response
    {
        $po = PoDetail::find()->where(['id' => $id, 'status' => 1])->one();
        if (!$po) {
            throw new NotFoundHttpException('PO not found.');
        }
        $path = Yii::getAlias('@webroot/') . ltrim((string) $po->file, '/');
        if (!is_file($path)) {
            throw new NotFoundHttpException('File missing on disk.');
        }
        return Yii::$app->response->sendFile($path, basename($path), ['inline' => true]);
    }

    // ───────────────────────────────────────────────────────────── helpers ──

    // ── Quote items, status and conversion ────────────────────────────────────

    /** Mark a quote Draft / Sent / Accepted / Rejected (Invoiced is set by the invoice). */
    public function actionSetStatus(int $id): Response
    {
        $model  = $this->findModel($id);
        $status = (int)Yii::$app->request->post('quote_status');
        $allowed = [ClientQuotation::QS_DRAFT, ClientQuotation::QS_SENT, ClientQuotation::QS_ACCEPTED, ClientQuotation::QS_REJECTED];
        if ((int)$model->quote_status === ClientQuotation::QS_INVOICED) {
            Yii::$app->session->setFlash('error', 'This quotation is already invoiced.');
        } elseif (!in_array($status, $allowed, true)) {
            Yii::$app->session->setFlash('error', 'Pick a valid status.');
        } else {
            $model->updateAttributes(['quote_status' => $status, 'modified_at' => date('Y-m-d H:i:s')]);
            Yii::$app->session->setFlash('success', 'Quotation marked ' . ClientQuotation::quoteStatusOptions()[$status] . '.');
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /** Copy a quote (header, items, terms) into a new Draft with a new number. */
    public function actionDuplicate(int $id): Response
    {
        $src  = $this->findModel($id);
        $copy = new ClientQuotation();
        foreach (['client_id', 'project_name', 'address', 'address_id', 'quote_for', 'deployment_type', 'alternate_quote_by', 'estimation_cost',
                  'tax_mode', 'terms', 'notes'] as $a) {
            if ($copy->hasAttribute($a)) {
                $copy->$a = $src->$a;
            }
        }
        $now = date('Y-m-d H:i:s');
        $copy->quote_date   = date('Y-m-d');
        $copy->valid_until  = date('Y-m-d', strtotime('+15 days'));
        $copy->created_at   = $now;
        $copy->modified_at  = $now;
        $copy->status       = 1;
        $copy->quote_status = ClientQuotation::QS_DRAFT;
        $copy->quoted_by    = (int)Yii::$app->user->id;
        $copy->parent_id    = null;
        $lines = array_map(fn(ClientQuotationItem $i) => $i->toLine(), $src->items);
        $totals = $lines ? LineItems::totals($lines, (int)($copy->tax_mode ?: LineItems::TAX_INTRA)) : null;

        if ($this->saveQuoteWithItems($copy, $lines, $totals, fn() => $this->saveQuotationWithGeneratedNumber($copy))) {
            Yii::$app->session->setFlash('success', 'Copied to new quotation ' . $copy->quote_no . ' (draft). Make your changes and save.');
            return $this->redirect(['update', 'id' => $copy->id]);
        }
        $errors = $copy->getFirstErrors();
        Yii::$app->session->setFlash('error', 'Could not duplicate the quotation: ' . (reset($errors) ?: 'please try again.'));
        return $this->redirect(['view', 'id' => $id]);
    }

    /** Accepted quote with items → new invoice pre-filled with the same lines. */
    public function actionCreateInvoice(int $id): Response
    {
        $model = $this->findModel($id);
        if (!\common\components\UserAccess::checkAccess('invoice/create')) {
            throw new \yii\web\ForbiddenHttpException('You do not have permission to create invoices.');
        }
        if ((int)$model->quote_status === ClientQuotation::QS_INVOICED && $model->invoice_id) {
            Yii::$app->session->setFlash('warning', 'This quotation was already invoiced.');
            return $this->redirect(['/invoice/view', 'id' => $model->invoice_id]);
        }
        if (!$model->hasItems()) {
            Yii::$app->session->setFlash('error', 'Only quotations with items can be turned into an invoice.');
            return $this->redirect(['view', 'id' => $id]);
        }
        if ((int)$model->quote_status !== ClientQuotation::QS_ACCEPTED) {
            Yii::$app->session->setFlash('error', 'Mark the quotation Accepted before creating the invoice.');
            return $this->redirect(['view', 'id' => $id]);
        }
        return $this->redirect(['/invoice/create', 'client_id' => $model->client_id, 'quotation_id' => $model->id, 'invoice_role' => 1]);
    }

    /**
     * Posted quotation items (QuoteItems[...]). No item rows = a quote without items
     * (estimate typed by hand, PDF attached) — the old way still works.
     * @return array{0: array, 1: ?array, 2: string[]} [lines, totals or null, errors]
     */
    private function readQuoteItems(ClientQuotation $model): array
    {
        $rows = Yii::$app->request->post('QuoteItems', []);
        $rows = is_array($rows) ? $rows : [];
        $filled = array_filter($rows, function ($r) {
            if (!is_array($r)) {
                return false;
            }
            foreach (['product_id', 'description', 'quantity', 'unit_price'] as $k) {
                if (isset($r[$k]) && is_scalar($r[$k]) && trim((string)$r[$k]) !== '') {
                    return true;
                }
            }
            return false;
        });
        if (!$filled) {
            // Green Divine quotes are always item-based.
            return [[], null, \backend\components\Crm::simple() ? ['Add at least one item.'] : []];
        }
        if (!in_array((int)$model->tax_mode, [LineItems::TAX_INTRA, LineItems::TAX_INTER, LineItems::TAX_NONE], true)) {
            $model->tax_mode = LineItems::taxModeFor($model->client);
        }
        [$lines, $totals, $errors] = LineItems::read(array_values($filled), (int)$model->tax_mode);
        return [$lines, $totals, $errors];
    }

    /**
     * Save the quote (via $save) and replace its items in one transaction. With items
     * the totals are stored and the estimation cost becomes the rounded total.
     */
    private function saveQuoteWithItems(ClientQuotation $model, array $lines, ?array $totals, callable $save): bool
    {
        if ($totals !== null) {
            $model->sub_total       = $totals['sub_total'];
            $model->discount_total  = $totals['discount_total'];
            $model->taxable_total   = $totals['taxable_total'];
            $model->gst_total       = $totals['gst_total'];
            $model->grand_total     = $totals['rounded_total'];
            $model->estimation_cost = (string)(int)$totals['rounded_total'];
        } elseif (!$model->isNewRecord && Yii::$app->request->post('QuoteItems') !== null) {
            // All items removed: back to a hand-typed estimate.
            $model->sub_total = $model->discount_total = $model->taxable_total = $model->gst_total = $model->grand_total = null;
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$save()) {
                $tx->rollBack();
                return false;
            }
            if ($totals !== null || Yii::$app->request->post('QuoteItems') !== null || $lines) {
                ClientQuotationItem::deleteAll(['quote_id' => $model->id]);
                $now = date('Y-m-d H:i:s');
                foreach (array_values($lines) as $i => $l) {
                    $item = new ClientQuotationItem();
                    $item->setAttributes([
                        'quote_id' => $model->id, 'product_id' => $l['product_id'], 'sort_order' => $i,
                        'description' => $l['description'], 'hsn_sac' => $l['hsn_sac'], 'unit' => $l['unit'],
                        'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'discount_percent' => $l['discount_percent'],
                        'gst_rate' => $l['gst_rate'], 'taxable_amount' => $l['taxable_amount'], 'gst_amount' => $l['gst_amount'],
                        'line_total' => $l['line_total'], 'created_at' => $now,
                    ], false);
                    if (!$item->save(false)) {
                        throw new \RuntimeException('item');
                    }
                }
            }
            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Quotation save failed: ' . $e->getMessage(), __METHOD__);
            $model->addError('id', 'The quotation could not be saved. Please try again.');
            return false;
        }
    }
    private function findModel(int $id): ClientQuotation
    {
        $m = ClientQuotation::find()->where(['id' => $id])->andWhere(['!=', 'status', 0])->one();
        if (!$m) throw new NotFoundHttpException('The requested quotation does not exist.');
        return $m;
    }

    private function clientSelectOptions(): array
    {
        $rows = ClientModel::find()
            ->select(['id', 'client_name', 'company_name'])
            ->where(['status' => 1])
            ->orderBy('client_name')
            ->asArray()
            ->all();
        // Delivery uses company_name as the displayed label in the form.
        return ArrayHelper::map($rows, 'id', 'company_name');
    }

    private function savePoFile(ClientQuotation $rootQuote, PoDetail $po): bool
    {
        $file = $po->po_file;
        if (!$file instanceof UploadedFile) {
            $po->addError('po_file', 'PO file is required.');
            return false;
        }
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'];
        if (!in_array(strtolower((string) $file->extension), $allowedExt, true)) {
            $po->addError('po_file', 'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.');
            return false;
        }
        $dir = Yii::getAlias('@webroot/uploads/po-details/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->baseName);
        $name  = time() . '_' . $rootQuote->id . '_' . $clean . '.' . $file->extension;
        if (!$file->saveAs($dir . $name)) {
            $po->addError('po_file', 'Could not save uploaded file.');
            return false;
        }
        $po->file = 'uploads/po-details/' . $name;
        return true;
    }

    private function saveQuotationFile(ClientQuotation $quote, UploadedFile $file): ?ClientQuotationFile
    {
        if (strtolower((string) $file->extension) !== 'pdf') {
            Yii::$app->session->setFlash('error', 'Quotation file must be a PDF.');
            return null;
        }
        $dir = Yii::getAlias('@webroot/uploads/client-quotation/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->baseName);
        $name  = time() . '_' . $clean . '.' . $file->extension;
        if (!$file->saveAs($dir . $name)) return null;
        $row = new ClientQuotationFile([
            'client_id'  => $quote->client_id,
            'quote_id'   => $quote->id,
            'file_path'  => 'uploads/client-quotation/' . $name,
            'created_at' => date('Y-m-d H:i:s'),
            'status'     => 1,
        ]);
        return $row->save(false) ? $row : null;
    }

    private function saveQuotationWithGeneratedNumber(ClientQuotation $model, ?ClientQuotation $root = null, int $maxAttempts = 10): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $model->quote_no = $root
                ? $this->generateNextAvailableChildQuoteNumber($root)
                : $this->generateNextAvailableQuoteNumber((int) $model->quote_for);
            try {
                if ($model->save()) return true;
            } catch (IntegrityException $e) {
                continue;
            }
            if (!$this->hasQuoteNumberConflict($model)) return false;
        }
        $model->addError('quote_no', 'Unable to generate a unique quote number — please retry.');
        return false;
    }

    private function hasQuoteNumberConflict(ClientQuotation $model): bool
    {
        foreach ($model->getErrors('quote_no') as $e) {
            if (stripos($e, 'unique') !== false || stripos($e, 'already') !== false) return true;
        }
        return false;
    }

    private function buildTentativeQuoteNumber(int $quoteFor, int $sequence): string
    {
        $prefix = $this->getQuotePrefix($quoteFor);
        return $prefix ? ($prefix . '/' . $this->getCurrentFinancialYear() . '/' . $this->formatQuoteSequence($sequence)) : '';
    }

    private function getQuotePrefix(int $quoteFor): ?string
    {
        // Matches delivery: 1 = IT (QIT), 2 = IOT (QIIOT). SH kept for legacy quote_for=3.
        return [1 => 'QIT', 2 => 'QIIOT', 3 => 'SH'][$quoteFor] ?? null;
    }

    private function getCurrentFinancialYear(): string
    {
        $m = (int) date('n');
        $y = (int) date('Y');
        [$s, $e] = $m >= 4 ? [$y, $y + 1] : [$y - 1, $y];
        return substr((string) $s, -2) . '-' . substr((string) $e, -2);
    }

    private function getNextQuoteSequenceNumber(): int
    {
        $max = 0;
        foreach (ClientQuotation::find()->select('quote_no')->where(['parent_id' => null])->column() as $qn) {
            if (preg_match('/\/(\d+)$/', (string) $qn, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max + 1;
    }

    private function generateNextAvailableQuoteNumber(int $quoteFor): string
    {
        $prefix = $this->getQuotePrefix($quoteFor);
        if (!$prefix) return '';
        $seq = $this->getNextQuoteSequenceNumber();
        $fy  = $this->getCurrentFinancialYear();
        while (true) {
            $qn = $prefix . '/' . $fy . '/' . $this->formatQuoteSequence($seq);
            if (!ClientQuotation::find()->where(['quote_no' => $qn])->exists()) return $qn;
            $seq++;
        }
    }

    private function generateNextAvailableChildQuoteNumber(ClientQuotation $root): string
    {
        $base = $root->quote_no;
        $max  = 0;
        foreach (ClientQuotation::find()->select('quote_no')->where(['parent_id' => $root->id])->column() as $qn) {
            if (preg_match('/-A(\d+)$/', (string) $qn, $m)) $max = max($max, (int) $m[1]);
        }
        $suffix = $max + 1;
        while (true) {
            $qn = $base . '-A' . $suffix;
            if (!ClientQuotation::find()->where(['quote_no' => $qn])->exists()) return $qn;
            $suffix++;
        }
    }

    private function formatQuoteSequence(int $s): string
    {
        return str_pad((string) $s, 3, '0', STR_PAD_LEFT);
    }

    private function formatModelErrors($model): string
    {
        $out = [];
        foreach ($model->getErrors() as $errs) foreach ($errs as $e) $out[] = $e;
        return $out ? implode(' ', $out) : 'Something went wrong.';
    }

    // ── Quote-By query helpers (mirror delivery exactly) ───────────────────

    private function buildQuoteByUserQuery(bool $requireOfficialMobile = false)
    {
        $q = User::find()
            ->alias('u')
            ->select(['u.user_id', 'u.username', 'u.usertype'])
            ->innerJoin(['d' => Developer::tableName()], 'd.user_id = u.user_id')
            ->where(['u.status' => 1])
            ->andWhere(['u.usertype' => [1, 2]])
            ->andWhere(['!=', 'u.user_id', (int) Yii::$app->user->id])
            ->groupBy(['u.user_id', 'u.username', 'u.usertype'])
            ->orderBy(['u.username' => SORT_ASC]);
        if ($requireOfficialMobile) {
            $q->andWhere(new Expression("TRIM(COALESCE(d.official_mobile_no, '')) <> ''"));
        }
        return $q;
    }

    private function buildPopupQuoteByUserQuery()
    {
        return Developer::find()
            ->alias('d')
            ->select(['d.user_id', 'd.developer_name', 'd.status'])
            ->where(['d.status' => 1])
            ->andWhere(['!=', 'd.user_id', (int) Yii::$app->user->id])
            ->groupBy(['d.user_id', 'd.developer_name', 'd.status'])
            ->orderBy(['d.developer_name' => SORT_ASC]);
    }

    private function searchPopupQuoteByUsers(string $term): array
    {
        $q = $this->buildPopupQuoteByUserQuery();
        $term = trim($term);
        if ($term !== '') {
            $q->andWhere(['or',
                ['like', 'd.developer_name', $term],
                ['like', 'd.user_id', $term],
            ]);
        }
        return array_map(
            fn(array $u) => ['id' => (int) $u['user_id'], 'text' => $this->formatPopupQuoteByUserLabel($u)],
            $q->asArray()->all()
        );
    }

    private function searchQuoteByUsers(string $term, bool $requireOfficialMobile = false): array
    {
        $q = $this->buildQuoteByUserQuery($requireOfficialMobile);
        $term = trim($term);
        if ($term !== '') {
            $q->andWhere(['or',
                ['like', 'u.username', $term],
                ['like', 'd.developer_name', $term],
                ['like', 'd.official_mobile_no', $term],
                ['like', 'd.official_email_id', $term],
            ]);
        }
        return array_map(
            fn(array $u) => ['id' => (int) $u['user_id'], 'text' => $this->formatQuoteByUserLabel($u)],
            $q->limit(20)->asArray()->all()
        );
    }

    private function getAdditionalQuoteBySelectedOptions(int $selectedUserId = 0): array
    {
        if ($selectedUserId <= 0 || $selectedUserId === (int) Yii::$app->user->id) return [];
        $u = User::find()->select(['user_id', 'username', 'usertype'])->where(['user_id' => $selectedUserId])->asArray()->one();
        if (!$u) return [];
        return [(int) $u['user_id'] => $this->formatQuoteByUserLabel($u)];
    }

    private function getActiveQuoteByUserOptions(): array
    {
        // Quote By filter: active employees only (usertype=2 = developer/employee,
        // status=1). Admins (usertype=1) and clients/sub-admins are excluded.
        $rows = User::find()
            ->select(['user_id', 'username'])
            ->where(['status' => 1])
            ->andWhere(['usertype' => 2])
            ->orderBy(['username' => SORT_ASC])
            ->asArray()
            ->all();
        return ArrayHelper::map($rows, 'user_id', 'username');
    }

    private function findPopupQuoteByUser(int $userId): ?Developer
    {
        if ($userId <= 0 || $userId === (int) Yii::$app->user->id) return null;
        return Developer::find()
            ->select(['user_id', 'developer_name', 'official_email_id', 'official_mobile_no', 'status'])
            ->where(['user_id' => $userId, 'status' => 1])
            ->one();
    }

    private function findCurrentOrPopupQuoteByUser(int $userId): ?Developer
    {
        if ($userId <= 0) return null;
        if ($userId === (int) Yii::$app->user->id) {
            return Developer::find()
                ->select(['user_id', 'developer_name', 'official_email_id', 'official_mobile_no', 'status'])
                ->where(['user_id' => $userId, 'status' => 1])
                ->one();
        }
        return $this->findPopupQuoteByUser($userId);
    }

    private function formatQuoteByUserLabel($u): string
    {
        $username = trim((string) ($u['username'] ?? $u->username ?? ''));
        return $username !== '' ? $username : 'User';
    }

    private function formatPopupQuoteByUserLabel($u): string
    {
        return trim((string) ($u['developer_name'] ?? $u->developer_name ?? ''));
    }

    private function findDeveloperForQuotationUser(int $quotedById): ?Developer
    {
        if ($quotedById <= 0) return null;
        $d = $this->findDeveloperByUserId($quotedById);
        return $d ?: Developer::findOne(['id' => $quotedById]);
    }

    private function findDeveloperByUserId(int $userId): ?Developer
    {
        if ($userId <= 0) return null;
        return Developer::find()->where(['user_id' => $userId])->orderBy(['id' => SORT_DESC])->one();
    }

    // ─────────────────────────────────────────────────────── PDF branding ──

    /**
     * Import every page of $sourcePath into the given Fpdi instance. If FPDI throws because
     * the source PDF uses a compression filter the free parser doesn't support (PDF >= 1.5
     * object streams / cross-reference streams), try to flatten the file with Ghostscript
     * to PDF 1.4 and retry. Returns true on success, false if the file ultimately could
     * not be merged (caller can skip the attachment without failing the whole preview).
     */
    private function mergePdfIntoFpdi(Fpdi $fpdi, string $sourcePath): bool
    {
        if (!is_file($sourcePath)) {
            return false;
        }
        try {
            $n = $fpdi->setSourceFile($sourcePath);
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $fpdi->importPage($i);
                $fpdi->AddPage();
                $fpdi->useTemplate($tpl);
            }
            return true;
        } catch (\Throwable $e) {
            // Fall through to Ghostscript flatten retry.
        }

        $flat = Yii::getAlias('@runtime') . '/flat_' . uniqid() . '.pdf';
        $cmd  = 'gs -dBATCH -dNOPAUSE -dQUIET -sDEVICE=pdfwrite '
              . '-dCompatibilityLevel=1.4 -dPDFSETTINGS=/default '
              . '-sOutputFile=' . escapeshellarg($flat) . ' '
              . escapeshellarg($sourcePath) . ' 2>&1';
        @exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($flat)) {
            return false;
        }
        try {
            $n = $fpdi->setSourceFile($flat);
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $fpdi->importPage($i);
                $fpdi->AddPage();
                $fpdi->useTemplate($tpl);
            }
            @unlink($flat);
            return true;
        } catch (\Throwable $e) {
            @unlink($flat);
            return false;
        }
    }

    private function createQuotationPdf(): Mpdf
    {
        $tempDir = Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);
        return new Mpdf([
            'tempDir'       => $tempDir,
            'margin_top'    => 30,
            'margin_bottom' => 20,
            'margin_left'   => 12,
            'margin_right'  => 12,
        ]);
    }

    private function applyQuotationPdfBranding(Mpdf $mpdf): void
    {
        $logoPath       = Yii::getAlias('@webroot/images/maestro-logo.png');
        $companyGst     = '33AAHCM8302P1Z6';
        $footerAddress  = 'Site No.4, Maestro Building SP Garden, Sathy Road, Kaapi Kadai, Vilankurichi, Coimbatore - 641035';
        $footerContact  = '7448417888    |    www.themaestro.in    |    info@themaestro.in';
        $footerCompany  = 'Maestro Technologies Service Private Limited';

        $header = '<table width="100%" style="border-collapse:collapse; font-family:Arial, sans-serif;">'
            . '<tr>'
            . '<td style="width:65%; border:none;">'
            . (is_file($logoPath) ? '<img src="' . $logoPath . '" style="width:230px;">' : '')
            . '</td>'
            . '<td style="width:35%; border:none; text-align:right; color:#1d75bb; font-size:14px; font-weight:700; padding-top:8px;">'
            . 'GST: ' . Html::encode($companyGst)
            . '</td></tr></table>';

        $footer = '<div style="font-family:Arial, sans-serif; text-align:center; color:#447fb8; font-size:12px; line-height:1.5;">'
            . '<div><h3>' . Html::encode($footerCompany) . '</h3></div>'
            . '<div><b>' . Html::encode($footerAddress) . '</b></div>'
            . '<div><b>' . Html::encode($footerContact) . '</b></div>'
            . '</div>';

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
    }
}
