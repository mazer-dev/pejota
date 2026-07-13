<?php

namespace Tests\Unit\Evolution;

use App\Services\Evolution\WhatsappJidNormalizer;
use Tests\TestCase;

class WhatsappJidNormalizerTest extends TestCase
{
    private WhatsappJidNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new WhatsappJidNormalizer;
    }

    public function test_candidates_expand_a_13_digit_brazilian_number_to_its_12_digit_form(): void
    {
        $this->assertSame(
            ['5554999371490', '555499371490'],
            $this->normalizer->candidates('5554999371490'),
        );
    }

    public function test_candidates_expand_a_12_digit_brazilian_number_to_its_13_digit_form(): void
    {
        $this->assertSame(
            ['555499371490', '5554999371490'],
            $this->normalizer->candidates('555499371490@s.whatsapp.net'),
        );
    }

    public function test_candidates_leave_non_brazilian_numbers_untouched(): void
    {
        $this->assertSame(['14155552671'], $this->normalizer->candidates('14155552671'));
        $this->assertSame(['4915112345678'], $this->normalizer->candidates('4915112345678'));
    }

    public function test_sender_number_resolves_lid_jids_through_the_payload_sender(): void
    {
        $number = $this->normalizer->senderNumber(
            ['sender' => '5581985573942@s.whatsapp.net'],
            ['key' => ['remoteJid' => '123456789012345@lid']],
        );

        $this->assertSame('5581985573942', $number);
    }

    public function test_sender_number_rejects_group_jids(): void
    {
        $number = $this->normalizer->senderNumber(
            ['sender' => '5581985573942@s.whatsapp.net'],
            ['key' => ['remoteJid' => '120363000000000000@g.us']],
        );

        $this->assertNull($number);
    }

    public function test_is_allowed_accepts_the_allowlisted_number_and_its_ninth_digit_variant(): void
    {
        $allowlist = ['5554999371490', '5581985573942'];

        $this->assertTrue($this->normalizer->isAllowed('5554999371490', $allowlist));
        $this->assertTrue($this->normalizer->isAllowed('555499371490', $allowlist));
        $this->assertTrue($this->normalizer->isAllowed('5581985573942', $allowlist));
        $this->assertTrue($this->normalizer->isAllowed('558185573942', $allowlist));
    }

    public function test_is_allowed_rejects_numbers_outside_the_allowlist(): void
    {
        $allowlist = ['5554999371490'];

        $this->assertFalse($this->normalizer->isAllowed('5511999990000', $allowlist));
        $this->assertFalse($this->normalizer->isAllowed(null, $allowlist));
        $this->assertFalse($this->normalizer->isAllowed('', $allowlist));
        $this->assertFalse($this->normalizer->isAllowed('5554999371490', []));
    }
}
