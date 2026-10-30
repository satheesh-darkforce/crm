<?php

namespace tests\unit\models;

use common\models\DomainRenewalHistory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DomainRenewalHistory model
 */
class DomainRenewalHistoryTest extends TestCase
{
    /**
     * Test that domain_id is required
     */
    public function testDomainIdRequired(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = null;
        $renewal->expiry_date = '2025-12-31';
        $renewal->amount_received = 500;

        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('domain_id', $renewal->errors);
    }

    /**
     * Test that expiry_date is required
     */
    public function testExpiryDateRequired(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = null;

        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('expiry_date', $renewal->errors);
    }

    /**
     * Test amount_received must be numeric and non-negative
     */
    public function testAmountReceivedValidation(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // Negative amount - invalid
        $renewal->amount_received = -100;
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('amount_received', $renewal->errors);

        // Non-numeric - invalid
        $renewal->amount_received = 'abc';
        $renewal->clearErrors();
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('amount_received', $renewal->errors);

        // Valid amounts
        $renewal->amount_received = 0;
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());

        $renewal->amount_received = 5000.50;
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test that created_by/updated_by must be integer (if provided)
     */
    public function testUserIdValidation(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // Invalid user IDs
        $renewal->created_by = 'abc';
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('created_by', $renewal->errors);

        // Valid user IDs
        $renewal->created_by = 1;
        $renewal->clearErrors();
        $renewal->updated_by = 2;
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test expiry date vs amount_received_date comparison
     */
    public function testDateComparison(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2024-01-01'; // Before received date
        $renewal->amount_received_date = '2024-06-01'; // After

        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('expiry_date', $renewal->errors);

        // Valid: expiry after received
        $renewal->expiry_date = '2025-12-31';
        $renewal->amount_received_date = '2024-06-01';
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());

        // Valid: no received date
        $renewal->amount_received_date = null;
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test string field length validation
     */
    public function testStringFieldLengthValidation(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // invoice_attachment - max 255 chars
        $renewal->invoice_attachment = str_repeat('a', 256); // Too long
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('invoice_attachment', $renewal->errors);

        // Valid length
        $renewal->invoice_attachment = 'uploads/renewal/file.pdf';
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());

        // recived_filename - max 255 chars
        $renewal->recived_filename = str_repeat('b', 256); // Too long
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('recived_filename', $renewal->errors);

        // Valid
        $renewal->recived_filename = 'invoice.pdf';
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test notes can be long text
     */
    public function testNotesFieldValidation(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';
        $renewal->notes = str_repeat('text ', 1000); // Very long text

        // Should not fail - notes is a text field with no max length
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test status must be integer (0 or 1)
     */
    public function testStatusValidation(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // Invalid status
        $renewal->status = 'active';
        $this->assertFalse($renewal->validate());
        $this->assertArrayHasKey('status', $renewal->errors);

        // Valid: 0 (deleted) or 1 (active)
        $renewal->status = 0;
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());

        $renewal->status = 1;
        $renewal->clearErrors();
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test safe date fields
     */
    public function testSafeDateFields(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // These should accept any value and not fail validation
        $renewal->amount_received_date = 'invalid-date';
        $renewal->created_at = 'also-invalid';
        $renewal->updated_at = 'still-invalid';

        // Validation should pass (safe fields don't validate format)
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test attribute labels
     */
    public function testAttributeLabels(): void
    {
        $renewal = new DomainRenewalHistory();
        $labels = $renewal->attributeLabels();

        $this->assertEquals('Domain', $labels['domain_id']);
        $this->assertEquals('Amount Received', $labels['amount_received']);
        $this->assertEquals('Amount Received Date', $labels['amount_received_date']);
        $this->assertEquals('Expiry Date', $labels['expiry_date']);
        $this->assertEquals('Notes', $labels['notes']);
        $this->assertEquals('Invoice Attachment', $labels['invoice_attachment']);
        $this->assertEquals('Received Filename', $labels['recived_filename']);
    }

    /**
     * Test domain relation exists
     */
    public function testDomainRelation(): void
    {
        $renewal = new DomainRenewalHistory();
        $this->assertTrue(method_exists($renewal, 'getDomain'));
    }

    /**
     * Test user relations exist
     */
    public function testUserRelations(): void
    {
        $renewal = new DomainRenewalHistory();
        $this->assertTrue(method_exists($renewal, 'getCreatedBy'));
        $this->assertTrue(method_exists($renewal, 'getUpdatedBy'));
    }

    /**
     * Test minimum valid renewal record
     */
    public function testMinimalValidRecord(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';

        // Should be valid with just required fields
        $this->assertTrue($renewal->validate());
    }

    /**
     * Test comprehensive valid record
     */
    public function testFullValidRecord(): void
    {
        $renewal = new DomainRenewalHistory();
        $renewal->domain_id = 1;
        $renewal->expiry_date = '2025-12-31';
        $renewal->amount_received = 500.00;
        $renewal->amount_received_date = '2024-12-01';
        $renewal->notes = 'Renewed with GoDaddy';
        $renewal->invoice_attachment = 'uploads/renewal/invoice_2024.pdf';
        $renewal->recived_filename = 'invoice_2024.pdf';
        $renewal->created_by = 1;
        $renewal->updated_by = 2;
        $renewal->status = 1;

        // All fields properly filled
        $this->assertTrue($renewal->validate());
    }
}
