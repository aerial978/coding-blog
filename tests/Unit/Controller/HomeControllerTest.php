<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    /**
     * @return UserModel&MockObject
     */
    private function mockUserModel(): UserModel
    {
        /** @var UserModel&MockObject $mock */
        $mock = $this->createMock(UserModel::class);

        return $mock;
    }

    /**
     * @return AuthCheckerInterface&MockObject
     */
    private function mockAuthChecker(): AuthCheckerInterface
    {
        /** @var AuthCheckerInterface&MockObject $mock */
        $mock = $this->createMock(AuthCheckerInterface::class);

        return $mock;
    }

    /**
     * @return CsrfTokenInterface&MockObject
     */
    private function mockCsrf(): CsrfTokenInterface
    {
        /** @var CsrfTokenInterface&MockObject $mock */
        $mock = $this->createMock(CsrfTokenInterface::class);

        return $mock;
    }

    /**
     * @return Request&MockObject
     */
    private function mockRequest(): Request
    {
        /** @var Request&MockObject $mock */
        $mock = $this->createMock(Request::class);

        return $mock;
    }

    /**
     * @return ResponderInterface&MockObject
     */
    private function mockResponder(): ResponderInterface
    {
        /** @var ResponderInterface&MockObject $mock */
        $mock = $this->createMock(ResponderInterface::class);

        return $mock;
    }

    public function testIndexRendersHomeTemplateWithUsersAndStaticData(): void
    {
        $request     = $this->mockRequest();
        $authChecker = $this->mockAuthChecker();
        $csrf        = $this->mockCsrf();
        $responder   = $this->mockResponder();

        $users = [
            (new UserEntity())->setUserId(1)->setUsername('Alice')->setEmail('a@example.test'),
            (new UserEntity())->setUserId(2)->setUsername('Bob')->setEmail('b@example.test'),
        ];

        $userModel = $this->mockUserModel();

        $userModel
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        $authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->with($request)
            ->willReturn(false);

        $csrf
            ->expects($this->never())
            ->method('generateToken');

        $responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'home/index.html.twig',
                $this->callback(function (array $data) use ($users): bool {
                    $this->assertSame('Home', $data['title']);
                    $this->assertSame('This is the home page.', $data['message']);
                    $this->assertSame($users, $data['users']);
                    $this->assertTrue($data['show_header']);
                    $this->assertSame('', $data['logout_csrf_token']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('is_authenticated', $data);

                    return true;
                })
            );

        $controller = new HomeController(
            $userModel,
            $request,
            $authChecker,
            $csrf,
            $responder,
        );

        $controller->index();
    }

    public function testIndexAddsLogoutTokenWhenUserIsAuthenticated(): void
    {
        $request     = $this->mockRequest();
        $authChecker = $this->mockAuthChecker();
        $csrf        = $this->mockCsrf();
        $responder   = $this->mockResponder();

        $userModel = $this->mockUserModel();

        $userModel
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->with($request)
            ->willReturn(true);

        $csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::LOGOUT)
            ->willReturn('logout-token-123');

        $responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'home/index.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertTrue($data['show_header']);
                    $this->assertSame('logout-token-123', $data['logout_csrf_token']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('is_authenticated', $data);

                    return true;
                })
            );

        $controller = new HomeController(
            $userModel,
            $request,
            $authChecker,
            $csrf,
            $responder,
        );

        $controller->index();
    }
}
