<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Core\View;
use DOMDocument;
use DOMXPath;

final class AdminUserEditViewTest extends FunctionalTestCase
{
    public function testAdminAccountIsRenderedReadOnly(): void
    {
        $view = new View();

        $html = $view->render('admin/users/edit.html.twig', [
            'title'             => 'Consulter un administrateur',
            'flashes'           => [],
            'csrf_token'        => 'test-csrf-token',
            'logout_csrf_token' => 'test-logout-token',
            'active_nav'        => 'users',
            'auth_user'         => [
                'roles' => ['ADMIN'],
            ],
            'user' => [
                'user_id'    => 10,
                'username'   => 'test_admin',
                'email'      => 'admin@example.test',
                'role'       => 'ADMIN',
                'status'     => 'active',
                'created_at' => '2026-10-25 10:00:00',
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

        foreach (['user_id', 'username', 'email', 'role', 'status'] as $id) {
            $inputs = $xpath->query(
                '//form[@id="adminUserEditForm"]//input[@id="' . $id . '"][@readonly]'
            );

            $this->assertNotFalse($inputs);
            $this->assertCount(1, $inputs, "Field {$id} must be read-only.");
        }

        $this->assertSame(
            0,
            $xpath->query('//form[@id="adminUserEditForm"]//select')->length
        );

        $this->assertSame(
            0,
            $xpath->query('//form[@id="adminUserEditForm"]//button[@type="submit"]')->length
        );

        $this->assertSame(
            0,
            $xpath->query('//form[@id="adminUserEditForm"]//input[@name="role"]')->length
        );

        $this->assertSame(
            0,
            $xpath->query('//form[@id="adminUserEditForm"]//input[@name="username"]')->length
        );

        $this->assertSame(
            0,
            $xpath->query('//script[contains(@src, "validation.user-edit.js")]')->length
        );
    }

    public function testActiveMemberAccountAllowsStatusModification(): void
    {
        $view = new View();

        $html = $view->render('admin/users/edit.html.twig', [
            'title'             => 'Modifier un utilisateur',
            'flashes'           => [],
            'csrf_token'        => 'test-csrf-token',
            'logout_csrf_token' => 'test-logout-token',
            'active_nav'        => 'users',
            'auth_user'         => [
                'roles' => ['ADMIN'],
            ],
            'user' => [
                'user_id'    => 20,
                'username'   => 'test_member',
                'email'      => 'member@example.test',
                'role'       => 'MEMBER',
                'status'     => 'active',
                'created_at' => '2026-10-25 10:00:00',
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

        foreach (['user_id', 'username', 'email', 'role'] as $id) {
            $inputs = $xpath->query(
                '//form[@id="adminUserEditForm"]//input[@id="' . $id . '"][@readonly]'
            );

            $this->assertNotFalse($inputs);
            $this->assertCount(1, $inputs, "Field {$id} must be read-only.");
        }

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//input[@type="hidden"]'
                . '[@name="role"][@value="MEMBER"]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//select[@name="status"]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//option'
                . '[@value="active"][@selected]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//option[@value="disabled"]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//button[@type="submit"]'
            )->length
        );

        $this->assertSame(
            0,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//input[@name="username"]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//script[contains(@src, "validation.user-edit.js")]'
            )->length
        );
    }

    public function testInactiveMemberAccountHasReadOnlyStatus(): void
    {
        $view = new View();

        $html = $view->render('admin/users/edit.html.twig', [
            'title'             => 'Modifier un utilisateur',
            'flashes'           => [],
            'csrf_token'        => 'test-csrf-token',
            'logout_csrf_token' => 'test-logout-token',
            'active_nav'        => 'users',
            'auth_user'         => [
                'roles' => ['ADMIN'],
            ],
            'user' => [
                'user_id'    => 30,
                'username'   => 'inactive_member',
                'email'      => 'inactive@example.test',
                'role'       => 'MEMBER',
                'status'     => 'inactive',
                'created_at' => '2026-10-25 10:00:00',
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

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]'
                . '//input[@id="status"][@value="inactive"][@readonly]'
            )->length
        );

        $this->assertSame(
            0,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//select[@name="status"]'
            )->length
        );

        $this->assertSame(
            1,
            $xpath->query(
                '//form[@id="adminUserEditForm"]'
                . '//input[@type="hidden"][@name="role"][@value="MEMBER"]'
            )->length
        );

        $this->assertSame(
            0,
            $xpath->query(
                '//form[@id="adminUserEditForm"]//button[@type="submit"]'
            )->length
        );
    }
}
