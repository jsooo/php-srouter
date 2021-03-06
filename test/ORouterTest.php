<?php
namespace Inhere\Route\Test;

use PHPUnit\Framework\TestCase;
use Inhere\Route\ORouter;

/**
 * @covers \Inhere\Route\ORouter
 */
class ORouterTest extends TestCase
{
    private function createRouter(array $config = [])
    {
        $r = new ORouter($config);
        $r->get('/', 'handler0');
        $r->get('/test', 'handler1');

        $r->get('/test1[/optional]', 'handler');

        $r->get('/my[/{name}[/{age}]]', 'handler2', [
            'params' => [
                'age' => '\d+'
            ],
            'defaults' => [
                'name' => 'God',
                'age' => 25,
            ]
        ]);

        $r->get('/hi/{name}', 'handler3', [
            'params' => [
                'name' => '\w+',
            ]
        ]);

        $r->post('/hi/{name}', 'handler4');
        $r->put('/hi/{name}', 'handler5');

        return $r;
    }

    public function testAddRoutes()
    {
        $router = $this->createRouter();

        $this->assertTrue(4 < $router->count());
        $this->assertCount(2, $router->getStaticRoutes());
    }

    public function testComplexRoute()
    {
        $router = $this->createRouter();

        // route: '/my[/{name}[/{age}]]'

        $ret = $router->match('/my', 'GET');

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/my', $path);
        $this->assertSame('handler2', $route['handler']);
        $this->assertArrayHasKey('matches', $route);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('God', $route['matches']['name']);

        $ret = $router->match('/my/tom', 'GET');

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/my/tom', $path);
        $this->assertSame('handler2', $route['handler']);
        $this->assertArrayHasKey('matches', $route);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('tom', $route['matches']['name']);
        $this->assertArrayHasKey('age', $route['matches']);
        $this->assertSame(25, $route['matches']['age']);

        $ret = $router->match('/my/tom/45', 'GET');

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/my/tom/45', $path);
        $this->assertSame('handler2', $route['handler']);
        $this->assertArrayHasKey('matches', $route);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('tom', $route['matches']['name']);
        $this->assertArrayHasKey('age', $route['matches']);
        $this->assertSame(45, (int)$route['matches']['age']);

        $ret = $router->match('/my/tom/not-match', 'GET');
        $this->assertSame(ORouter::NOT_FOUND, $ret[0]);
    }

    public function testStaticRoute()
    {
        $router = $this->createRouter();

        // 1
        $ret = $router->match('/', 'GET');

        $this->assertCount(3, $ret);

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/', $path);
        $this->assertSame('handler0', $route['handler']);
    }

    public function testOptionalParamRoute()
    {
        $router = $this->createRouter();

        // route: '/test1[/optional]'
        $ret = $router->match('/test1', 'GET');

        $this->assertCount(3, $ret);

        list($status, , $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('handler', $route['handler']);

        // route: '/test1[/optional]'
        $ret = $router->match('/test1/optional', 'GET');

        $this->assertCount(3, $ret);

        list($status, , $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('handler', $route['handler']);

    }

    public function testParamRoute()
    {
        $router = $this->createRouter();

        // route: /hi/{name}
        $ret = $router->match('/hi/3456', 'GET');

        $this->assertCount(3, $ret);

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/hi/3456', $path);
        $this->assertSame('/hi/{name}', $route['original']);
        $this->assertSame('handler3', $route['handler']);

        // route: /hi/{name}
        $ret = $router->match('/hi/tom', 'GET');

        $this->assertCount(3, $ret);

        list($status, $path, $route) = $ret;

        $this->assertSame(ORouter::FOUND, $status);
        $this->assertSame('/hi/tom', $path);
        $this->assertSame('/hi/{name}', $route['original']);
        $this->assertSame('handler3', $route['handler']);
        $this->assertArrayHasKey('matches', $route);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('tom', $route['matches']['name']);
    }

    public function testNotFound()
    {
        $router = $this->createRouter();

        $ret = $router->match('/not-exist', 'GET');

        $this->assertCount(3, $ret);

        list($status, $path, ) = $ret;

        $this->assertSame(ORouter::NOT_FOUND, $status);
        $this->assertSame('/not-exist', $path);

        $ret = $router->match('/hi', 'GET');

        $this->assertCount(3, $ret);

        list($status, $path, ) = $ret;

        $this->assertSame(ORouter::NOT_FOUND, $status);
        $this->assertSame('/hi', $path);
    }

    public function testMethods()
    {
        $router = $this->createRouter([
            'notAllowedAsNotFound' => false,
        ]);

        // route: /hi/{name}
        $ret = $router->match('/hi/tom', 'post');

        $this->assertCount(3, $ret);

        list($status, , $route) = $ret;
        $this->assertSame(ORouter::FOUND, $status);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('handler4', $route['handler']);

        // route: /hi/{name}
        $ret = $router->match('/hi/tom', 'put');

        list($status, , $route) = $ret;
        $this->assertCount(3, $ret);
        $this->assertSame(ORouter::FOUND, $status);
        $this->assertArrayHasKey('name', $route['matches']);
        $this->assertSame('handler5', $route['handler']);

        // route: /hi/{name}
        $ret = $router->match('/hi/tom', 'delete');

        list($status, , $methods) = $ret;
        $this->assertCount(3, $ret);
        $this->assertSame(ORouter::METHOD_NOT_ALLOWED, $status);
        $this->assertCount(3, $methods);
    }
}
