<?php

declare(strict_types=1);

namespace Mcfedr\AwsPushBundle\Tests\Message;

use Faker\Provider\Base;
use Faker\Provider\Lorem;
use Mcfedr\AwsPushBundle\Exception\MessageTooLongException;
use Mcfedr\AwsPushBundle\Message\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    /**
     * @dataProvider tooLongMessage
     * @expectedException \Mcfedr\AwsPushBundle\Exception\MessageTooLongException
     */
    public function testTooLong(Message $message)
    {
        try {
            echo json_encode($message);
        } catch (\Exception $e) {
            if ($e->getPrevious() instanceof MessageTooLongException) {
                throw $e->getPrevious();
            }
            throw $e;
        }
    }

    public function tooLongMessage()
    {
        $ios = new Message();
        $ios->setCustom([
            'data' => Lorem::text(3000),
        ]);
        $ios->setPlatforms([Message::PLATFORM_APNS]);

        $android = new Message();
        $android->setCustom([
            'data' => Lorem::text(6000),
        ]);
        $android->setPlatforms([Message::PLATFORM_GCM]);

        $amazon = new Message();
        $amazon->setCustom([
            'data' => Lorem::text(7000),
        ]);
        $amazon->setPlatforms([Message::PLATFORM_ADM]);

        return [
            [
                $ios,
            ],
            [
                $android,
            ],
            [
                $amazon,
            ],
        ];
    }

    /**
     * @dataProvider shortMessage
     */
    public function testShortMessage(Message $message)
    {
        $this->assertNotEmpty(json_encode($message));
    }

    public function shortMessage()
    {
        $ios = new Message();
        $ios->setCustom([
            'data' => Lorem::text(2000),
        ]);
        $ios->setPlatforms([Message::PLATFORM_APNS]);

        $android = new Message();
        $android->setCustom([
            'data' => Lorem::text(4050),
        ]);
        $android->setPlatforms([Message::PLATFORM_GCM]);

        $amazon = new Message();
        $amazon->setCustom([
            'data' => Lorem::text(5050),
        ]);
        $amazon->setPlatforms([Message::PLATFORM_ADM]);

        return [
            [
                $ios,
            ],
            [
                $android,
            ],
            [
                $amazon,
            ],
        ];
    }

    /**
     * @dataProvider trimMessage
     */
    public function testTrimMessage(Message $message)
    {
        $this->assertNotEmpty(json_encode($message));
    }

    public function trimMessage()
    {
        return [
            [
                new Message(Lorem::text(10000)),
            ],
        ];
    }

    /**
     * @dataProvider text
     */
    public function testTextMessageStructure($text, $title)
    {
        $message = new Message($text);
        $message->setTitle($title);

        $string = (string) $message;
        $data = json_decode($string, true);

        $this->assertInternalType('array', $data);
        $this->assertCount(7, $data);
        $this->assertArrayHasKey('default', $data);
        $this->assertArrayHasKey('APNS', $data);
        $this->assertArrayHasKey('APNS_VOIP', $data);
        $this->assertArrayHasKey('APNS_SANDBOX', $data);
        $this->assertArrayHasKey('APNS_VOIP_SANDBOX', $data);
        $this->assertArrayHasKey('GCM', $data);
        $this->assertArrayHasKey('ADM', $data);

        $this->assertEquals($text, $data['default'], 'Default should be the text of the message');
        $this->assertEquals($data['APNS'], $data['APNS_SANDBOX'], 'The APNS and APNS_SANDBOX should match');

        $apnsData = json_decode($data['APNS'], true);
        $this->assertInternalType('array', $apnsData);
        $this->assertCount(1, $apnsData);
        $this->assertArrayHasKey('aps', $apnsData);

        $this->assertInternalType('array', $apnsData['aps']);
        $this->assertCount(1, $apnsData['aps']);
        $this->assertArrayHasKey('alert', $apnsData['aps']);

        $this->assertInternalType('array', $apnsData['aps']['alert']);
        $this->assertArrayHasKey('body', $apnsData['aps']['alert']);

        $this->assertEquals($text, $apnsData['aps']['alert']['body'], 'APNS.aps.alert.body should be the text of the message');
        $this->assertEquals($title, $apnsData['aps']['alert']['title'], 'APNS.aps.alert.title should be the title of the message');

        $gcmData = json_decode($data['GCM'], true);
        $this->assertInternalType('array', $gcmData);
        $this->assertCount(5, $gcmData);
        $this->assertArrayHasKey('data', $gcmData);
        $this->assertArrayHasKey('collapse_key', $gcmData);
        $this->assertArrayHasKey('time_to_live', $gcmData);
        $this->assertArrayHasKey('delay_while_idle', $gcmData);
        $this->assertArrayHasKey('priority', $gcmData);

        $this->assertInternalType('array', $gcmData['data']);
        $this->assertCount(1, $gcmData['data']);
        $this->assertEquals($text, $gcmData['data']['message'], 'GCM.data.message should be the text of the message');

        $admData = json_decode($data['ADM'], true);
        $this->assertInternalType('array', $admData);
        $this->assertCount(2, $admData);
        $this->assertArrayHasKey('data', $admData);
        $this->assertArrayHasKey('expiresAfter', $admData);

        $this->assertInternalType('array', $admData['data']);
        $this->assertCount(1, $admData['data']);
        $this->assertEquals($text, $admData['data']['message'], 'ADM.data.message should be the text of the message');
    }

    public function text()
    {
        return [
            [Lorem::text(1000), Lorem::text(50)],
        ];
    }

    /**
     * @dataProvider localized
     */
    public function testLocalizedMessageStructure($text, $key, $args)
    {
        $message = new Message($text);
        $message->setLocalizedKey($key);
        $message->setLocalizedArguments($args);

        $message->setTitleLocalizedKey($key);
        $message->setTitleLocalizedArguments($args);

        $string = (string) $message;
        $data = json_decode($string, true);

        $this->assertEquals($text, $data['default'], 'Default should be just the text of the message');

        $apnsData = json_decode($data['APNS'], true);
        $this->assertCount(1, $apnsData);
        $this->assertCount(1, $apnsData['aps']);
        $this->assertArrayHasKey('alert', $apnsData['aps']);
        $this->assertInternalType('array', $apnsData['aps']['alert']);
        $this->assertCount(4, $apnsData['aps']['alert']);
        $this->assertEquals($key, $apnsData['aps']['alert']['loc-key'], 'APNS.aps.alert.loc-key should be the key of the message');
        $this->assertEquals($args, $apnsData['aps']['alert']['loc-args'], 'APNS.aps.alert.loc-args should be the args of the message');
        $this->assertEquals($key, $apnsData['aps']['alert']['title-loc-key'], 'APNS.aps.alert.title-loc-key should be the args of the title');
        $this->assertEquals($args, $apnsData['aps']['alert']['title-loc-args'], 'APNS.aps.alert.title-loc-args should be the args of the title');

        $gcmData = json_decode($data['GCM'], true);
        $this->assertCount(5, $gcmData);
        $this->assertCount(3, $gcmData['data']);
        $this->assertEquals($text, $gcmData['data']['message'], 'GCM.data.message should be the text of the message');
        $this->assertEquals($key, $gcmData['data']['message-loc-key'], 'GCM.data.message-loc-key should be the key of the message');
        $this->assertEquals($args, $gcmData['data']['message-loc-args'], 'GCM.data.message-loc-args should be the args of the message');

        $admData = json_decode($data['ADM'], true);
        $this->assertCount(2, $admData);
        $this->assertCount(3, $admData['data']);
        $this->assertEquals($text, $admData['data']['message'], 'ADM.data.message should be the text of the message');
        $this->assertEquals($key, $admData['data']['message-loc-key'], 'ADM.data.message-loc-key should be the key of the message');
        $this->assertEquals(json_encode($args), $admData['data']['message-loc-args_json'], 'ADM.data.message-loc-args should be the args of the message');
    }

    public function localized()
    {
        return [
            [Lorem::text(1000), Lorem::text(50), Lorem::words(3)],
        ];
    }

    /**
     * @dataProvider localizedNoArgs
     */
    public function testLocalizedNoArgsMessageStructure($text, $key)
    {
        $message = new Message($text);
        $message->setLocalizedKey($key);

        $message->setTitleLocalizedKey($key);

        $string = (string) $message;
        $data = json_decode($string, true);

        $this->assertEquals($text, $data['default'], 'Default should be just the text of the message');

        $apnsData = json_decode($data['APNS'], true);
        $this->assertCount(1, $apnsData);
        $this->assertCount(1, $apnsData['aps']);
        $this->assertArrayHasKey('alert', $apnsData['aps']);
        $this->assertInternalType('array', $apnsData['aps']['alert']);
        $this->assertCount(2, $apnsData['aps']['alert']);
        $this->assertEquals($key, $apnsData['aps']['alert']['loc-key'], 'APNS.aps.alert.loc-key should be the key of the message');
        $this->assertEquals($key, $apnsData['aps']['alert']['title-loc-key'], 'APNS.aps.alert.title-loc-key should be the key of the title');

        $gcmData = json_decode($data['GCM'], true);
        $this->assertCount(5, $gcmData);
        $this->assertCount(2, $gcmData['data']);
        $this->assertEquals($text, $gcmData['data']['message'], 'GCM.data.message should be the text of the message');
        $this->assertEquals($key, $gcmData['data']['message-loc-key'], 'GCM.data.message-loc-key should be the key of the message');

        $admData = json_decode($data['ADM'], true);
        $this->assertCount(2, $admData);
        $this->assertCount(2, $admData['data']);
        $this->assertEquals($text, $admData['data']['message'], 'ADM.data.message should be the text of the message');
        $this->assertEquals($key, $admData['data']['message-loc-key'], 'ADM.data.message-loc-key should be the key of the message');
    }

    public function localizedNoArgs()
    {
        return [
            [Lorem::text(1000), Lorem::text(50)],
        ];
    }

    public function testAdmCustomData()
    {
        $message = new Message();
        $message->setCustom([
            'simple' => 'Hello',
            'complicated' => [
                'inner' => 'values',
            ],
        ]);

        $string = (string) $message;
        $data = json_decode($string, true);

        $admData = json_decode($data['ADM'], true);

        $this->assertCount(2, $admData['data']);
        $this->assertArrayHasKey('simple', $admData['data']);
        $this->assertEquals('Hello', $admData['data']['simple']);
        $this->assertArrayHasKey('complicated_json', $admData['data']);
        $this->assertEquals(json_encode([
            'inner' => 'values',
        ]), $admData['data']['complicated_json']);
    }

    /**
     * @dataProvider ttl
     */
    public function testTtl($ttl)
    {
        $message = new Message(Lorem::text(1000));
        $message->setTtl($ttl);

        $string = (string) $message;
        $data = json_decode($string, true);

        $gcmData = json_decode($data['GCM'], true);
        $this->assertEquals($ttl, $gcmData['time_to_live']);

        $admData = json_decode($data['ADM'], true);
        $this->assertEquals($ttl, $admData['expiresAfter']);
    }

    public function ttl()
    {
        return [
            [Base::numberBetween(60, 2678400)],
        ];
    }
}
