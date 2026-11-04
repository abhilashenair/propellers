<?php

namespace Tests\Feature;

use App\AwbBarcode;
use App\Models\AirwayBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwbBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_contains_printable_barcode_with_its_awb_number_underneath(): void
    {
        $user = User::factory()->create();
        $bill = AirwayBill::factory()->create(['number' => 'AWB-123456789', 'created_by' => $user->id]);
        $response = $this->actingAs($user)->get('/bills/'.$bill->id)->assertOk();
        $response->assertSee('aria-label="AWB barcode AWB-123456789"', false)
            ->assertSeeInOrder(['<svg', '</svg>', 'class="awb-barcode-number">AWB-123456789'], false);
    }

    public function test_code128_a_has_known_start_data_checksum_stop_and_quiet_zones(): void
    {
        $barcode = (new AwbBarcode)->encode('A');
        $this->assertSame(66, $barcode['width']);
        $this->assertSame([
            ['x' => 10, 'width' => 2], ['x' => 13, 'width' => 1], ['x' => 16, 'width' => 1],
            ['x' => 21, 'width' => 1], ['x' => 23, 'width' => 1], ['x' => 27, 'width' => 2],
            ['x' => 32, 'width' => 1], ['x' => 36, 'width' => 1], ['x' => 38, 'width' => 2],
            ['x' => 43, 'width' => 2], ['x' => 48, 'width' => 3], ['x' => 52, 'width' => 1], ['x' => 54, 'width' => 2],
        ], $barcode['bars']);
    }

    public function test_ascii_case_punctuation_and_maximum_length_are_supported_without_changing_identifier(): void
    {
        $encoder = new AwbBarcode;
        $this->assertNotNull($encoder->encode('awb-123/ABC.45'));
        $this->assertNotSame($encoder->encode('ABC'), $encoder->encode('abc'));
        $this->assertSame(715, $encoder->encode(str_repeat('9', 60))['width']);
        $this->assertNull($encoder->encode(''));
        $this->assertNull($encoder->encode('अ123'));
        $this->assertNull($encoder->encode("ABC\n123"));
    }

    public function test_unsupported_identifiers_keep_bill_accessible_and_escape_markup(): void
    {
        $bill = AirwayBill::factory()->create(['number' => 'अ<script>alert(1)</script>']);
        $this->actingAs(User::factory()->create())->get('/bills/'.$bill->id)->assertOk()
            ->assertSee('Barcode unavailable')->assertSee($bill->number)->assertDontSee('<script>alert(1)</script>', false);
    }
}
