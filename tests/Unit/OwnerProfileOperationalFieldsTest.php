<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Company;
use App\Models\LorryPartyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerProfileOperationalFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_owner_settlement_and_operational_details(): void
    {
        User::factory()->create(['role' => 'super admin']);
        $company = Company::factory()->create();

        $owner = LorryPartyProfile::create([
            'company_id' => $company->id,
            'type' => LorryPartyProfile::TYPE_OWNER,
            'name' => 'Sharma Transport',
            'phone' => '9876543210',
            'alternate_phone' => '9876543211',
            'pan_number' => 'ABCDE1234F',
            'gstin' => '27ABCDE1234F1Z5',
            'bank_name' => 'State Bank of India',
            'bank_account_holder_name' => 'Sharma Transport',
            'bank_account_no' => '1234567890',
            'ifsc_code' => 'SBIN0001234',
            'upi_id' => 'sharma@upi',
            'status' => 'active',
            'notes' => 'Settlement every Friday.',
        ]);

        $this->assertDatabaseHas('lorry_party_profiles', [
            'id' => $owner->id,
            'pan_number' => 'ABCDE1234F',
            'gstin' => '27ABCDE1234F1Z5',
            'ifsc_code' => 'SBIN0001234',
            'status' => 'active',
        ]);
    }
}
