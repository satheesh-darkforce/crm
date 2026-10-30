<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Migrated from Yii1: User model + UserIdentity component
 *
 * Table: user  (PK: user_id — bigint, NOT auto-named 'id')
 *
 * User types (usertype column):
 *   1 = Admin
 *   2 = Developer
 *   3 = Client
 *   4 = Sub-admin
 *
 * Password storage:
 *   - Legacy Yii1 data: MD5 hash (32 chars)
 *   - Passwords:        SHA1 hashing (same as Yii1 application)
 *
 * @property int    $user_id
 * @property string $username
 * @property string $password
 * @property int    $usertype
 * @property string $last_password_changed_date
 * @property int    $force_password_change
 * @property int    $status
 * @property int    $is_tester
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_ACTIVE   = 1;
    const STATUS_INACTIVE = 0;

    const TYPE_ADMIN     = 1;
    const TYPE_DEVELOPER = 2;
    const TYPE_CLIENT    = 3;
    const TYPE_SUBADMIN  = 4;

    public static function tableName(): string
    {
        return 'user';
    }

    public function rules(): array
    {
        return [
            [['username', 'password', 'usertype', 'status'], 'required'],
            [['usertype', 'status', 'force_password_change', 'is_tester'], 'integer'],
            [['last_password_changed_date'], 'safe'],
            [['username'], 'string', 'max' => 50],
            [['password'], 'string', 'max' => 200],
            [['username'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'user_id'  => 'User ID',
            'username' => 'Username',
            'usertype' => 'User Type',
            'status'   => 'Status',
        ];
    }

    // ── IdentityInterface ────────────────────────────────────────────────────

    public static function findIdentity($id): ?static
    {
        return static::findOne(['user_id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds identity by API Bearer token.
     * Looks up the crm_token table — migrated from Yii1 CrmToken / WebserviceForm::checkToken().
     */
    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        $crmToken = CrmToken::findOne(['token' => $token, 'status' => 1]);
        if (!$crmToken) {
            return null;
        }
        return static::findOne(['user_id' => $crmToken->user_id, 'status' => self::STATUS_ACTIVE]);
    }

    public function getId(): int|string
    {
        return $this->user_id;
    }

    public function getAuthKey(): string
    {
        return md5($this->user_id . $this->username . $this->password);
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    // ── Authentication ───────────────────────────────────────────────────────

    public static function findByUsername(string $username): ?static
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Validates password against stored SHA1 hash.
     */
    public function validatePassword(string $password): bool
    {
        return $this->password === sha1($password);
    }

    /**
     * Sets password using SHA1 hash.
     */
    public function setPassword(string $password): void
    {
        $this->password = sha1($password);
    }

    // ── API Token Management ─────────────────────────────────────────────────

    /**
     * Issues a new API Bearer token stored in crm_token table.
     * Migrated from Yii1: WebserviceController::actionLogin() token generation.
     */
    public function generateApiToken(?string $deviceType = null, ?string $deviceId = null, ?string $pushId = null): string
    {
        if ($deviceId) {
            CrmToken::updateAll(['status' => 0], ['user_id' => $this->user_id, 'device_id' => $deviceId]);
        }

        $token             = Yii::$app->security->generateRandomString(64);
        $crmToken          = new CrmToken();
        $crmToken->user_id    = $this->user_id;
        $crmToken->token      = $token;
        $crmToken->device_type = $deviceType ? (int)$deviceType : null;
        $crmToken->device_id  = $deviceId;
        $crmToken->push_id    = $pushId;
        $crmToken->created_at = date('Y-m-d H:i:s');
        $crmToken->status     = 1;
        $crmToken->save(false);

        return $token;
    }

    public function revokeApiToken(): void
    {
        CrmToken::updateAll(['status' => 0], ['user_id' => $this->user_id, 'status' => 1]);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['user_id' => 'user_id']);
    }

    public function getEmployee(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Employee::class, ['user_id' => 'user_id']);
    }

    public function getAdmin(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Admin::class, ['user_id' => 'user_id']);
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['user_id' => 'user_id']);
    }

    public function getCrmTokens(): \yii\db\ActiveQuery
    {
        return $this->hasMany(CrmToken::class, ['user_id' => 'user_id']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isAdmin(): bool     { return (int)$this->usertype === self::TYPE_ADMIN; }
    public function isDeveloper(): bool { return (int)$this->usertype === self::TYPE_DEVELOPER; }
    public function isClient(): bool    { return (int)$this->usertype === self::TYPE_CLIENT; }
    public function isSubAdmin(): bool  { return (int)$this->usertype === self::TYPE_SUBADMIN; }

    /**
     * Returns the developer.id for this user (via the developer relation).
     * Works for any user type that has a linked developer record (admin, developer, sub-admin).
     */
    public function getDeveloperId(): ?int
    {
        $dev = $this->developer;
        return $dev ? (int)$dev->id : null;
    }

    public function getTypeName(): string
    {
        return match ((int)$this->usertype) {
            self::TYPE_ADMIN     => 'Admin',
            self::TYPE_DEVELOPER => 'Developer',
            self::TYPE_CLIENT    => 'Client',
            self::TYPE_SUBADMIN  => 'Sub-Admin',
            default              => 'Unknown',
        };
    }
}
