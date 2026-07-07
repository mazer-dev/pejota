<?php

namespace Tests\Feature\Sending;

use App\Support\SubjectSanitizer;
use Tests\TestCase;

class SubjectSanitizerTest extends TestCase
{
    public function test_removes_cr_lf_to_prevent_header_injection(): void
    {
        $this->assertSame('a b', SubjectSanitizer::sanitize("a\r\nb"));
        $this->assertSame('a b c', SubjectSanitizer::sanitize("a\nb\rc"));
    }

    public function test_trims_and_collapses(): void
    {
        $this->assertSame('Invoice INV-1', SubjectSanitizer::sanitize("  Invoice INV-1\n"));
    }

    public function test_leaves_clean_subject_intact(): void
    {
        $this->assertSame('Invoice INV-1 - Acme', SubjectSanitizer::sanitize('Invoice INV-1 - Acme'));
    }
}
