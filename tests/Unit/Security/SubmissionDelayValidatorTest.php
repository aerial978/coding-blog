<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\SubmissionDelayValidator;
use PHPUnit\Framework\TestCase;
use Tests\Double\SessionFake;

final class SubmissionDelayValidatorTest extends TestCase
{
    public function testAssertDelayPassedIsNeutralWhenNoStartFound(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // Pas de marqueur de début => comportement neutre (pas d’exception)
        $validator->assertDelayPassed('register', 10, 1000);

        self::assertTrue(true);
    }

    public function testAssertDelayPassedBlocksWhenTooFast(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // Start = maintenant => elapsed ~ 0
        $session->set('form_start_register', time());
        $session->set('form_min_ok_register', 0);

        try {
            $validator->assertDelayPassed('register', 10, 1000);
            self::fail('Une SuspiciousSubmissionException était attendue.');
        } catch (SuspiciousSubmissionException $e) {
            // Ajuste l’assertion selon ton implémentation réelle de l’exception
            self::assertSame('min_delay_not_met', $e->getReason());
        }
    }

    public function testAssertDelayPassedBlocksWhenTooLate(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // Start = il y a 100 secondes
        $session->set('form_start_register', time() - 100);
        $session->set('form_min_ok_register', 0);

        try {
            // max=10 => elapsed (100) > max
            $validator->assertDelayPassed('register', 1, 10);
            self::fail('Une SuspiciousSubmissionException était attendue.');
        } catch (SuspiciousSubmissionException $e) {
            self::assertSame('max_delay_exceeded', $e->getReason());
        }
    }

    public function testFirstValidSubmissionSetsMinOkFlag(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // Start = il y a 20 secondes => elapsed=20
        $session->set('form_start_register', time() - 20);
        $session->set('form_min_ok_register', 0);

        $validator->assertDelayPassed('register', 10, 1000);

        self::assertSame(1, $session->get('form_min_ok_register'));
    }

    public function testMinDelayIsRelaxedAfterMinOk(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // Min déjà validé
        $session->set('form_start_register', time());
        $session->set('form_min_ok_register', 1);

        // elapsed ~ 0 mais minOk=1 => ne doit pas lever min_delay_not_met
        $validator->assertDelayPassed('register', 10, 1000);

        self::assertTrue(true);
    }

    public function testMarkFormStartStoresStartAndResetsMinOkFlag(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        $session->set('form_min_ok_register', 1);

        $validator->markFormStart('register');

        self::assertIsInt($session->get('form_start_register'));
        self::assertNull($session->get('form_min_ok_register'));
    }

    public function testAssertDelayPassedIsNeutralWhenStartIsZeroOrInvalid(): void
    {
        $session   = new SessionFake();
        $validator = new SubmissionDelayValidator($session);

        // start présent mais invalide -> cast en int donne 0
        $session->set('form_start_register', 0);

        // Ne doit pas lever d'exception
        $validator->assertDelayPassed('register', 10, 1000);

        self::assertTrue(true);
    }
}
