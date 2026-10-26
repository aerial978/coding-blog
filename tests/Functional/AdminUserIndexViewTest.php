<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Core\View;
use DOMDocument;
use DOMXPath;

final class AdminUserIndexViewTest extends FunctionalTestCase
{
    public function testAdminAccountCannotBeDeletedFromUserList(): void
    {
        $view = new View();

        $html = $view->render('admin/users/index.html.twig', [
            'title'             => 'Gestion des utilisateurs',
            'flashes'           => [],
            'csrf_token'        => 'test-csrf-token',
            'logout_csrf_token' => 'test-logout-token',
            'active_nav'        => 'users',
            'auth_user'         => [
                'roles' => ['ADMIN'],
            ],
            'users' => [
                [
                    'user_id'                => 10,
                    'username'               => 'test_admin',
                    'email'                  => 'admin@example.test',
                    'role'                   => 'ADMIN',
                    'status'                 => 'active',
                    'created_at'             => '2026-10-25 10:00:00',
                    'can_delete'             => false,
                    'delete_disabled_reason' => 'Suppression des administrateurs interdite.',
                ],
            ],
        ]);

        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($dom->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);

        // An administrator must have a consultation link.
        $this->assertSame(
            1,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="10"]]'
                . '//a[contains(@href, "/admin/users/10/edit")]'
                . '[normalize-space()="Consulter"]'
            )->length
        );

        // No modification link must be displayed.
        $this->assertSame(
            0,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="10"]]'
                . '//a[normalize-space()="Modifier"]'
            )->length
        );

        // The deletion button must be disabled.
        $this->assertSame(
            1,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="10"]]'
                . '//button[@disabled][normalize-space()="Supprimer"]'
            )->length
        );

        // No active deletion trigger must exist for this administrator.
        $this->assertSame(
            0,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="10"]]'
                . '//button[@data-delete-user-id]'
            )->length
        );

        // The reason for disabling deletion must be visible.
        $this->assertSame(
            1,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="10"]]'
                . '//span[contains(@class, "delete-disabled-reason")]'
                . '[contains(normalize-space(), "Suppression des administrateurs interdite.")]'
            )->length
        );
    }

    public function testMemberAccountCanBeEditedAndDeletedFromUserList(): void
    {
        $view = new View();

        $html = $view->render('admin/users/index.html.twig', [
            'title'             => 'Gestion des utilisateurs',
            'flashes'           => [],
            'csrf_token'        => 'test-csrf-token',
            'logout_csrf_token' => 'test-logout-token',
            'active_nav'        => 'users',
            'auth_user'         => [
                'roles' => ['ADMIN'],
            ],
            'users' => [
                [
                    'user_id'                => 20,
                    'username'               => 'test_member',
                    'email'                  => 'member@example.test',
                    'role'                   => 'MEMBER',
                    'status'                 => 'active',
                    'created_at'             => '2026-10-25 10:00:00',
                    'can_delete'             => true,
                    'delete_disabled_reason' => '',
                ],
            ],
        ]);

        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($dom->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);

        // A member must have an edit link.
        $this->assertSame(
            1,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="20"]]'
                . '//a[contains(@href, "/admin/users/20/edit")]'
                . '[normalize-space()="Modifier"]'
            )->length
        );

        // No consultation-only link must be displayed.
        $this->assertSame(
            0,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="20"]]'
                . '//a[normalize-space()="Consulter"]'
            )->length
        );

        // The deletion trigger must be available.
        $this->assertSame(
            1,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="20"]]'
                . '//button[@data-delete-user-id="20"]'
                . '[@data-delete-url]'
                . '[not(@disabled)]'
            )->length
        );

        // No disabled deletion button must be displayed.
        $this->assertSame(
            0,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="20"]]'
                . '//button[@disabled][normalize-space()="Supprimer"]'
            )->length
        );

        // No deletion restriction message must be displayed.
        $this->assertSame(
            0,
            $xpath->query(
                '//tbody/tr[td[1][normalize-space()="20"]]'
                . '//span[contains(@class, "delete-disabled-reason")]'
            )->length
        );
    }
}
