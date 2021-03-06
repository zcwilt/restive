<?php

namespace Tests\Unit;

use Restive\ApiQueryParser;
use Restive\Exceptions\ParserParameterCountException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Restive\ParserFactory;
use Tests\DatabaseTestCase;
use Illuminate\Support\Facades\Request;
use Tests\Fixtures\Controllers\Api\ZcwiltUserController;
use Restive\ModelMakerFactory;
use Tests\Fixtures\Models\ZcwiltUser;

class ParserWithParseTest extends DatabaseTestCase
{
    public function testIncludesParserParseTestNoParams()
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->getParser('with');
        $this->expectException(ParserParameterCountException::class);
        $parser->parse('');
    }

    public function testIncludesParserParseTestWithParams()
    {
        $api = new ApiQueryParser(new ParserFactory());
        Request::instance()->query->set('with', 'foo,bar');
        $api->parseRequest(Request::instance());
        $api->buildParsers();
        $tokenized = $api->getQueryParts()[0]->getTokenized()[0];
        $this->assertTrue($tokenized['field'] === 'foo');
        $tokenized = $api->getQueryParts()[0]->getTokenized()[1];
        $this->assertTrue($tokenized['field'] === 'bar');
    }

    public function testIncludesParserWithDummyData()
    {
        $testResult = ZcWiltUser::with('posts')->get()->toArray();
        Request::instance()->query->set('with', 'posts');
        $result  = $this->getRequestResults();
        $this->assertTrue(count($result) === count($testResult));
        $this->assertTrue(count($result[0]['posts']) === count($testResult[0]['posts']));
    }

    public function testIncludesParserWithDummyDataInvalidWith()
    {
        Request::instance()->query->set('with', 'foos');
        $this->expectException(RelationNotFoundException::class);
        $this->getRequestResults();
    }

    public function testControllerIndexWithIncludesParserPass()
    {
        $testResult = ZcWiltUser::with('posts')->where('id', '=', 2)->get()->toArray();
        $request = Request::create('/index', 'GET', [
            'where' => 'id:eq:2', 'with' => 'posts'
        ]);
        $controller = new ZcwiltUserController(new ModelMakerFactory());
        $response = $controller->index($request);
        $response = json_decode($response->getContent());
        $this->assertTrue(count($response->data) === 1);
        $this->assertTrue(count($response->data[0]->posts) === count($testResult[0]['posts']));
    }

    public function testControllerIndexWithIncludesParserFail()
    {
        $request = Request::create('/index', 'GET', [
            'where' => 'id:eq:1', 'with' => 'foo'
        ]);
        $controller = new ZcwiltUserController(new ModelMakerFactory());
        $response = $controller->index($request);
        $response = json_decode($response->getContent());
        $message = $response->error->message;
        $this->assertContains('Call to undefined relationship', $message);
    }
}
