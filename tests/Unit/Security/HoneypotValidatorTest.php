<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\HoneypotValidator;
use PHPUnit\Framework\TestCase;

final class HoneypotValidatorTest extends TestCase
{
    public function testAssertCleanPassesWhenFieldIsMissing(): void
    {
        $validator = new HoneypotValidator('fax');

        $validator->assertClean([
            'email' => 'user@example.com',
        ]);

        self::assertTrue(true);
    }

    public function testAssertCleanPassesWhenFieldIsEmptyOrWhitespace(): void
    {
        $validator = new HoneypotValidator('fax');

        $validator->assertClean(['fax' => '']);
        $validator->assertClean(['fax' => '   ']);
        $validator->assertClean(['fax' => "\n\t  "]);

        self::assertTrue(true);
    }

    public function testAssertCleanThrowsWhenFieldIsFilled(): void
    {
        $validator = new HoneypotValidator('fax');

        try {
            $validator->assertClean(['fax' => 'bot-value']);
            self::fail('Une SuspiciousSubmissionException était attendue.');
        } catch (SuspiciousSubmissionException $e) {
            self::assertSame('honeypot_triggered', $e->getReason());
            self::assertSame(['field' => 'fax'], $e->getContext());
        }
    }

    public function testCustomFieldNameIsUsed(): void
    {
        $validator = new HoneypotValidator('website');

        self::assertSame('website', $validator->fieldName());

        try {
            $validator->assertClean(['website' => 'spam']);
            self::fail('Une SuspiciousSubmissionException était attendue.');
        } catch (SuspiciousSubmissionException $e) {
            self::assertSame('honeypot_triggered', $e->getReason());
            self::assertSame(['field' => 'website'], $e->getContext());
        }
    }
}
