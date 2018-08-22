<?php

namespace Themosis\Tests\Core;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Themosis\Core\Http\FormRequest;

class FormRequestTest extends TestCase
{
    /**
     * @var array
     */
    protected $mocks = [];

    public function testValidatedMethodReturnsValidatedData()
    {
        $request = $this->createRequest(['name' => 'something', 'with' => 'brush']);

        $request->validateResolved();

        $this->assertEquals(['name' => 'something'], $request->validated());
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testValidateThrowsWhenValidationFails()
    {
        $request = $this->createRequest(['no' => 'name']);

        $this->mocks['redirect']->expects($this->any())->method('withInput');
        $this->mocks['redirect']->expects($this->any())->method('withErrors');

        $request->validateResolved();
    }

    /**
     * @expectedException \Illuminate\Auth\Access\AuthorizationException
     */
    public function testValidateMethodThrowsWhenAuthorizationFails()
    {
        $this->createRequest([], CoreTestFormRequestForbiddenStub::class)->validateResolved();
    }

    public function testPrepareForValidationRunsBeforeValidation()
    {
        $request = $this->createRequest([], CoreTestFormRequestHooks::class);
        $request->validateResolved();

        $this->assertEquals(['name' => 'Themosis'], $request->validated());
    }

    protected function createRequest($payload = [], $class = CoreTestFormRequestStub::class)
    {
        $container = tap(new Container(), function ($container) {
            $container->instance(
                Factory::class,
                $this->createValidationFactory($container)
            );
        });

        $request = $class::create('/', 'GET', $payload);

        return $request->setContainer($container)
            ->setRedirector($this->createRedirector($request));
    }

    /**
     * Create a validation factory.
     *
     * @param \Illuminate\Container\Container $container
     *
     * @return Factory
     */
    protected function createValidationFactory($container)
    {
        $translator = $this->getMockBuilder(Translator::class)
            ->setMethods(['trans', 'transChoice', 'getLocale', 'setLocale', 'rules'])
            ->getMock();

        $translator->expects($this->any())->method('trans')->willReturn('error');

        return new Factory($translator, $container);
    }

    /**
     * Create a mock redirector.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Routing\Redirector
     */
    protected function createRedirector($request)
    {
        $redirector = $this->mocks['redirector'] = $this->getMockBuilder(Redirector::class)
            ->setConstructorArgs([$generator = $this->createUrlGenerator()])
            ->setMethods(['getUrlGenerator', 'to'])
            ->getMock();

        $redirector->expects($this->any())->method('getUrlGenerator')->willReturn($generator);

        $redirector->expects($this->any())->method('to')->willReturn($this->createRedirectResponse());

        $generator->expects($this->any())->method('previous')->willReturn('previous');

        return $redirector;
    }

    /**
     * Create a URL generator.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createUrlGenerator()
    {
        return $this->mocks['generator'] = $this->getMockBuilder(UrlGenerator::class)
            ->disableOriginalConstructor()
            ->setMethods(['previous'])
            ->getMock();
    }

    /**
     * Create a redirect response.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createRedirectResponse()
    {
        return $this->mocks['redirect'] = $this->getMockBuilder(RedirectResponse::class)
            ->disableOriginalConstructor()
            ->setMethods(['withInput', 'WithErrors'])
            ->getMock();
    }
}

class CoreTestFormRequestStub extends FormRequest
{
    public function rules()
    {
        return ['name' => 'required'];
    }

    public function authorize()
    {
        return true;
    }
}

class CoreTestFormRequestForbiddenStub extends FormRequest
{
    public function rules()
    {
        return [];
    }

    public function authorize()
    {
        return false;
    }
}

class CoreTestFormRequestHooks extends FormRequest
{
    public function rules()
    {
        return ['name' => 'required'];
    }

    public function authorize()
    {
        return true;
    }

    public function prepareForValidation()
    {
        $this->replace(['name' => 'Themosis']);
    }
}
