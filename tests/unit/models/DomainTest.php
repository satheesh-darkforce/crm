<?php

namespace tests\unit\models;

use common\models\Domain;
use common\models\DomainRenewalHistory;
use common\models\ClientModel;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Domain model
 */
class DomainTest extends TestCase
{
    private Domain $domain;
    private ClientModel $client;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialize fixtures if needed
    }

    /**
     * Test that domain requires client_id
     */
    public function testClientIdRequired(): void
    {
        $domain = new Domain();
        $domain->client_id = null;
        $domain->domain_name = 'example.com';
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('client_id', $domain->errors);
    }

    /**
     * Test that domain name is required
     */
    public function testDomainNameRequired(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->domain_name = '';
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('domain_name', $domain->errors);
    }

    /**
     * Test domain name format validation (plain domains and URLs)
     */
    public function testDomainNameFormat(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';
        $domain->domain_status = 1;

        // ✅ Valid plain domain names
        $validDomains = [
            'example.com',
            'my-site.co.uk',
            'conceptmetalsolutions.com',
            'rajservicetraders.in',
            'test.org',
        ];

        foreach ($validDomains as $name) {
            $domain->domain_name = $name;
            $domain->clearErrors();
            $domain->validate();
            $this->assertArrayNotHasKey('domain_name', $domain->errors, "Failed for: $name");
        }

        // ✅ Valid URLs with http://
        $validUrls = [
            'http://example.com',
            'http://example.com/',
            'http://my-site.co.uk/',
            'http://rajservicetraders.in',
            'http://dreammanpower.com/',
        ];

        foreach ($validUrls as $url) {
            $domain->domain_name = $url;
            $domain->clearErrors();
            $domain->validate();
            $this->assertArrayNotHasKey('domain_name', $domain->errors, "Failed for: $url");
        }

        // ✅ Valid URLs with https://
        $validHttpsUrls = [
            'https://example.com',
            'https://example.com/',
            'https://www.example.com/',
            'https://kansaenterprises.in',
            'https://claude.ai',
            'https://www.akshathaparentalcare.com/',
        ];

        foreach ($validHttpsUrls as $url) {
            $domain->domain_name = $url;
            $domain->clearErrors();
            $domain->validate();
            $this->assertArrayNotHasKey('domain_name', $domain->errors, "Failed for: $url");
        }

        // ❌ Invalid domain names
        $invalidDomains = [
            'invalid domain.com', // spaces not allowed
            'just-text', // no TLD
            'http://', // no domain
            'example', // no TLD
            '123', // just numbers
        ];

        foreach ($invalidDomains as $name) {
            $domain->domain_name = $name;
            $domain->clearErrors();
            $domain->validate();
            $this->assertArrayHasKey('domain_name', $domain->errors, "Should fail for: $name");
        }
    }

    /**
     * Test expiry date must be after purchase date
     */
    public function testExpiryDateValidation(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->domain_name = 'example.com';
        $domain->date_of_purchase = '2024-12-31';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2024-01-01'; // Before purchase - invalid

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('expiry_date', $domain->errors);
    }

    /**
     * Test domain status is required
     */
    public function testDomainStatusRequired(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->domain_name = 'example.com';
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';
        $domain->domain_status = null; // Missing required field

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('domain_status', $domain->errors);
    }

    /**
     * Test domain status must be valid enum value
     */
    public function testDomainStatusValidation(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->domain_name = 'example.com';
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';
        $domain->domain_status = 99; // Invalid status

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('domain_status', $domain->errors);

        // Valid statuses
        foreach ([1, 2, 3, 4, 5] as $status) {
            $domain->domain_status = $status;
            $domain->clearErrors();
            $domain->validate();
            $this->assertArrayNotHasKey('domain_status', $domain->errors);
        }
    }

    /**
     * Test price validation (must be non-negative)
     */
    public function testPriceValidation(): void
    {
        $domain = new Domain();
        $domain->client_id = 1;
        $domain->domain_name = 'example.com';
        $domain->date_of_purchase = '2024-01-01';
        $domain->service_provider = 'GoDaddy';
        $domain->expiry_date = '2025-01-01';
        $domain->price = -100; // Negative price - invalid

        $this->assertFalse($domain->validate());
        $this->assertArrayHasKey('price', $domain->errors);

        // Valid prices
        $domain->price = 0;
        $domain->clearErrors();
        $this->assertTrue($domain->validate());

        $domain->price = 500.99;
        $domain->clearErrors();
        $this->assertTrue($domain->validate());
    }

    /**
     * Test domain relation to client
     */
    public function testClientRelation(): void
    {
        $domain = new Domain();
        $this->assertTrue(method_exists($domain, 'getClient'));
    }

    /**
     * Test domain relation to renewal histories
     */
    public function testRenewalHistoriesRelation(): void
    {
        $domain = new Domain();
        $this->assertTrue(method_exists($domain, 'getRenewalHistories'));
    }

    /**
     * Test domain status labels
     */
    public function testDomainStatusLabels(): void
    {
        $labels = Domain::domainStatusLabels();

        $this->assertArrayHasKey(1, $labels);
        $this->assertArrayHasKey(2, $labels);
        $this->assertArrayHasKey(3, $labels);
        $this->assertArrayHasKey(4, $labels);
        $this->assertArrayHasKey(5, $labels);

        $this->assertEquals('Live', $labels[1]);
        $this->assertEquals('Expired', $labels[2]);
        $this->assertEquals('Need to Follow', $labels[3]);
        $this->assertEquals('Pending Payment', $labels[4]);
        $this->assertEquals('Pending Renewal', $labels[5]);
    }
}
