<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AccountPasswordFormTest extends TestCase
{
    public function testAccountPageRendersPasswordChangeForm(): void
    {
        $view = new View();

        $html = $view->render('account/index.html.twig', [
            'title'               => 'Mon compte',
            'active_nav'          => 'account',
            'csrf_token'          => 'username-test-token',
            'password_csrf_token' => 'password-test-token',
            'logout_csrf_token'   => 'logout-test-token',
            'auth_user'           => [
                'roles' => ['MEMBER'],
            ],
            'flashes' => [],
            'account' => [
                'username'          => 'michael',
                'email'             => 'michael@example.com',
                'email_2fa_enabled' => false,
                'role'              => 'MEMBER',
                'status'            => 'active',
                'created_at'        => '2026-01-15 10:30:00',
            ],
        ]);

        $dom = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($dom->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($dom);

        $passwordForms = $xpath->query(
            '//form[@id="accountPasswordForm"]'
        );

        $this->assertNotFalse($passwordForms);
        $this->assertCount(1, $passwordForms);

        $passwordForm = $passwordForms->item(0);
        $this->assertInstanceOf(\DOMElement::class, $passwordForm);

        $this->assertSame('post', strtolower($passwordForm->getAttribute('method')));
        $this->assertStringEndsWith(
            '/account/password',
            $passwordForm->getAttribute('action')
        );

        foreach (
            [
            'current_password',
            'new_password',
            'password_confirmation',
            ] as $fieldName
        ) {
            $fields = $xpath->query(
                './/input[@name="' . $fieldName . '"]',
                $passwordForm
            );

            $this->assertNotFalse($fields);
            $this->assertCount(1, $fields);

            $field = $fields->item(0);
            $this->assertInstanceOf(\DOMElement::class, $field);
            $this->assertSame('password', $field->getAttribute('type'));
            $this->assertSame('', $field->getAttribute('value'));
            $this->assertTrue($field->hasAttribute('required'));
        }

        $csrfFields = $xpath->query(
            './/input[@name="csrf_token"]',
            $passwordForm
        );

        $this->assertNotFalse($csrfFields);
        $this->assertCount(1, $csrfFields);

        $csrfField = $csrfFields->item(0);
        $this->assertInstanceOf(\DOMElement::class, $csrfField);
        $this->assertSame(
            'password-test-token',
            $csrfField->getAttribute('value')
        );

        $newPasswordFields = $xpath->query(
            './/input[@name="new_password"]',
            $passwordForm
        );

        $this->assertNotFalse($newPasswordFields);

        $newPasswordField = $newPasswordFields->item(0);
        $this->assertInstanceOf(\DOMElement::class, $newPasswordField);
        $this->assertSame('12', $newPasswordField->getAttribute('minlength'));
        $this->assertSame(
            'new-password',
            $newPasswordField->getAttribute('autocomplete')
        );
    }
}
