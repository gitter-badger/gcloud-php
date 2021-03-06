<?php
/**
 * Copyright 2015 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Gcloud\Tests\Storage;

use Google\Gcloud\Storage\Bucket;
use Prophecy\Argument;

class BucketTest extends \PHPUnit_Framework_TestCase
{
    public $connection;

    public function setUp()
    {
        $this->connection = $this->prophesize('Google\Gcloud\Storage\Connection\ConnectionInterface');
    }

    public function testGetsAcl()
    {
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertInstanceOf('Google\Gcloud\Storage\Acl', $bucket->acl());
    }

    public function testGetsDefaultAcl()
    {
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertInstanceOf('Google\Gcloud\Storage\Acl', $bucket->defaultAcl());
    }

    public function testDoesExistTrue()
    {
        $this->connection->getBucket(Argument::any())->willReturn(['name' => 'bucket']);
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertTrue($bucket->exists());
    }

    public function testDoesExistFalse()
    {
        $this->connection->getBucket(Argument::any())->willThrow(new \Exception());
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertFalse($bucket->exists());
    }

    public function testGetObject()
    {
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertInstanceOf('Google\Gcloud\Storage\Object', $bucket->object('peter-venkman.jpg'));
    }

    public function testInstantiateObjectWithGeneration()
    {
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $object = $bucket->object('peter-venkman.jpg', [
            'generation' => '5'
        ]);

        $this->assertInstanceOf('Google\Gcloud\Storage\Object', $object);
    }

    public function testGetsObjectsWithoutToken()
    {
        $this->connection->listObjects(Argument::any())->willReturn([
            'items' => [
                ['name' => 'file.txt']
            ]
        ]);

        $bucket = new Bucket($this->connection->reveal(), 'bucket');
        $objects = iterator_to_array($bucket->objects());

        $this->assertEquals('file.txt', $objects[0]->getName());
    }

    public function testGetsObjectsWithToken()
    {
        $this->connection->listObjects(Argument::any())->willReturn(
            [
                'nextPageToken' => 'token',
                'items' => [
                    ['name' => 'file.txt']
                ]
            ],
                [
                'items' => [
                    ['name' => 'file2.txt']
                ]
            ]
        );

        $bucket = new Bucket($this->connection->reveal(), 'bucket');
        $objects = iterator_to_array($bucket->objects());

        $this->assertEquals('file2.txt', $objects[1]->getName());
    }

    public function testDelete()
    {
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertNull($bucket->delete());
    }

    public function testUpdatesData()
    {
        $versioningData = [
            'versioning' => [
                'enabled' => true
            ]
        ];
        $this->connection->patchBucket(Argument::any())->willReturn(['name' => 'bucket'] + $versioningData);
        $bucket = new Bucket($this->connection->reveal(), 'bucket', [
            'name' => 'bucket',
            'versioning' => [
                'enabled' => false
            ]
        ]);

        $bucket->update($versioningData);

        $this->assertTrue($bucket->getInfo()['versioning']['enabled']);
    }

    public function testGetsInfo()
    {
        $bucketInfo = [
            'name' => 'bucket',
            'etag' => 'ABC',
            'kind' => 'storage#bucket'
        ];
        $bucket = new Bucket($this->connection->reveal(), 'bucket', $bucketInfo);

        $this->assertEquals($bucketInfo, $bucket->getInfo());
    }

    public function testGetsInfoWithForce()
    {
        $bucketInfo = [
            'name' => 'bucket',
            'etag' => 'ABC',
            'kind' => 'storage#bucket'
        ];
        $this->connection->getBucket(Argument::any())->willReturn($bucketInfo);
        $bucket = new Bucket($this->connection->reveal(), 'bucket');

        $this->assertEquals($bucketInfo, $bucket->getInfo(['force' => true]));
    }

    public function testGetsName()
    {
        $bucket = new Bucket($this->connection->reveal(), $name = 'bucket');

        $this->assertEquals($name, $bucket->getName());
    }
}
