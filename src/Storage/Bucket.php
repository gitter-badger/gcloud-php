<?php
/**
 * Copyright 2015 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Gcloud\Storage;

use Google\Gcloud\Storage\Connection\ConnectionInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Buckets are the basic containers that hold your data. Everything that you
 * store in Google Cloud Storage must be contained in a bucket.
 */
class Bucket
{
    /**
     * @var ConnectionInterface Represents a connection to Cloud Storage.
     */
    private $connection;

    /**
     * @var array The bucket's identity.
     */
    private $identity;

    /**
     * @var array The bucket's metadata.
     */
    private $data;

    /**
     * @var Acl ACL for the bucket.
     */
    private $acl;

    /**
     * @var Acl Default ACL for objects created within the bucket.
     */
    private $defaultAcl;

    /**
     * @param ConnectionInterface $connection Represents a connection to Cloud
     *        Storage.
     * @param string $name The bucket's name.
     * @param array $data The bucket's metadata.
     */
    public function __construct(ConnectionInterface $connection, $name, array $data = null)
    {
        $this->connection = $connection;
        $this->identity = ['bucket' => $name];
        $this->data = $data;
        $this->acl = new Acl($this->connection, 'bucketAccessControls', $this->identity);
        $this->defaultAcl = new Acl($this->connection, 'defaultObjectAccessControls', $this->identity);
    }

    /**
     * Configure ACL for this bucket.
     *
     * Example:
     * ```
     * use Google\Gcloud\Storage\Acl;
     *
     * $acl = $bucket->acl();
     * $acl->add('allAuthenticatedUsers', Acl::ROLE_READER);
     * ```
     *
     * @see https://cloud.google.com/storage/docs/access-control More about Access Control Lists
     * @return Acl An ACL instance configured to handle the bucket's access
     *         control policies.
     */
    public function acl()
    {
        return $this->acl;
    }

    /**
     * Configure default object ACL for this bucket.
     *
     * Example:
     * ```
     * use Google\Gcloud\Storage\Acl;
     *
     * $acl = $bucket->defaultAcl();
     * $acl->add('allAuthenticatedUsers', Acl::ROLE_READER);
     * ```
     *
     * @see https://cloud.google.com/storage/docs/access-control More about Access Control Lists
     * @return Acl An ACL instance configured to handle the bucket's default
     *         object access control policies.
     */
    public function defaultAcl()
    {
        return $this->defaultAcl;
    }

    /**
     * Check whether or not the bucket exists.
     *
     * Example:
     * ```
     * $bucket->exists();
     * ```
     *
     * @return bool
     */
    public function exists()
    {
        try {
            $this->connection->getBucket($this->identity + ['fields' => 'name']);
        } catch (\Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Upload data.
     *
     * Example:
     * ```
     * $bucket->upload(
     *     fopen('image.jpg', 'r'),
     *     'image.jpg',
     *     ['contentType' => 'image/jpeg']
     * );
     * ```
     *
     * @param string|resource|StreamInterface $data
     * @param string $destination Name of where the file will be stored.
     * @param array $options {
     *      Configuration options.
     *
     *      @type string $contentType The content type.
     * }
     * @return \Google\Gcloud\Storage\Object
     */
    public function upload($data, $destination, array $options = [])
    {
        $response = $this->connection->uploadObject($options + [
            'bucket' => $this->identity['bucket'],
            'data' => $data,
            'name' => $destination
        ]);

        return new Object($this->connection, $destination, $this->identity['bucket'], null, $response);
    }

    /**
     * Upload
     *
     * Example:
     * ```
     * ```
     *
     * @param string $path Path to the file to be uploaded.
     * @param array $options Configuration options.
     * @return \Google\Gcloud\Storage\Object
     */
    public function uploadFromPath($path, array $options = [])
    {

    }

    /**
     * Lazily instantiates an object. There are no network requests made at this
     * point. To see the operations that can be performed on an object please
     * see {@see Google\Gcloud\Storage\Object}.
     *
     * Example:
     * ```
     * $object = $bucket->object('file.txt');
     * ```
     *
     * @param string $name The name of the object to request.
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $generation Request a specific revision of the object.
     * }
     * @return \Google\Gcloud\Storage\Object
     */
    public function object($name, array $options = [])
    {
        $generation = isset($options['generation']) ? $options['generation'] : null;

        return new Object($this->connection, $name, $this->identity['bucket'], $generation);
    }

    /**
     * Fetches all objects in the bucket.
     *
     * Example:
     * ```
     * // Get all objects beginning with the prefix 'photo'
     * $objects = $bucket->objects([
     *     'prefix' => 'photo',
     *     'fields' => 'items/name,nextPageToken'
     * ]);
     *
     * foreach ($objects as $object) {
     *     var_dump($object->getName());
     * }
     * ```
     *
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $delimiter Returns results in a directory-like mode.
     *           Results will contain only objects whose names, aside from the
     *           prefix, do not contain delimiter. Objects whose names, aside
     *           from the prefix, contain delimiter will have their name,
     *           truncated after the delimiter, returned in prefixes. Duplicate
     *           prefixes are omitted.
     *     @type integer $maxResults Maximum number of results to return per
     *           request. Defaults to 1000.
     *     @type string $prefix Filter results with this prefix.
     *     @type string $projection Determines which properties to return. May
     *           be either 'full' or 'noAcl'.
     *     @type bool $versions If true, lists all versions of an object as
     *           distinct results. The default is false.
     *     @type string $fields Selector which will cause the response to only
     *           return the specified fields.
     * }
     * @return \Generator
     */
    public function objects(array $options = [])
    {
        $options['pageToken'] = null;

        do {
            $response = $this->connection->listObjects($options + $this->identity);

            foreach ($response['items'] as $object) {
                // @todo when versions === true pass generation
                yield new Object($this->connection, $object['name'], $this->identity['bucket'], null, $object);
            }

            $options['pageToken'] = isset($response['nextPageToken']) ? $response['nextPageToken'] : null;
        } while ($options['pageToken']);
    }

    /**
     * @todo should this return something significant to the user?
     * Delete the bucket.
     *
     * Example:
     * ```
     * $bucket->delete();
     * ```
     *
     * @param array $options {
     *     Configuration options.
     *     @type string $ifMetagenerationMatch If set, only deletes the bucket
     *           if its metageneration matches this value.
     *     @type string $ifMetagenerationNotMatch If set, only deletes the
     *           bucket if its metageneration does not match this value.
     * }
     * @return void
     */
    public function delete(array $options = [])
    {
        $this->connection->deleteBucket($options + $this->identity);
    }

    /**
     * Update the bucket. Upon receiving a result the local bucket's data will
     * be updated.
     *
     * Example:
     * ```
     * // Enable logging on an existing bucket.
     * $bucket->update([
     *     'logging' => [
     *         'logBucket' => 'myBucket',
     *         'logObjectPrefix' => 'prefix'
     *     ]
     * ]);
     * ```
     *
     * @see https://goo.gl/KgufNr Learn more about configuring request options
     *       at the bucket patch API documentation.
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $ifMetagenerationMatch Makes the return of the bucket
     *           metadata conditional on whether the bucket's current
     *           metageneration matches the given value.
     *     @type string $ifMetagenerationNotMatch Makes the return of the bucket
     *           metadata conditional on whether the bucket's current
     *           metageneration does not match the given value.
     *     @type string $predefinedAcl Apply a predefined set of access controls
     *           to this bucket.
     *     @type string $predefinedDefaultObjectAcl Apply a predefined set of
     *           default object access controls to this bucket.
     *     @type string $projection Determines which properties to return. May
     *           be either 'full' or 'noAcl'.
     *     @type string $fields Selector which will cause the response to only
     *           return the specified fields.
     *     @type array $acl Access controls on the bucket.
     *     @type array $cors The bucket's Cross-Origin Resource Sharing (CORS)
     *           configuration.
     *     @type array $defaultObjectAcl Default access controls to apply to new
     *           objects when no ACL is provided.
     *     @type array $lifecycle The bucket's lifecycle configuration.
     *     @type array $logging The bucket's logging configuration, which
     *           defines the destination bucket and optional name prefix for the
     *           current bucket's logs.
     *     @type array $versioning The bucket's versioning configuration.
     *     @type array $website The bucket's website configuration.
     * }
     * @return array
     */
    public function update(array $options = [])
    {
        $this->data = $this->connection->patchBucket($options + $this->identity);

        return $this->data;
    }

    /**
     * Retrieves the bucket's details.
     *
     * Example:
     * ```
     * $info = $bucket->getInfo();
     * echo $info['location'];
     * ```
     *
     * @param array $options {
     *     Configuration options.
     *
     *     @type bool $force If true fetches fresh data, otherwise returns data
     *           stored locally if it exists.
     *     @type string $ifMetagenerationMatch Makes the return of the bucket
     *           metadata conditional on whether the bucket's current
     *           metageneration matches the given value.
     *     @type string $ifMetagenerationNotMatch Makes the return of the bucket
     *           metadata conditional on whether the bucket's current
     *           metageneration does not match the given value.
     *     @type string $projection Determines which properties to return. May
     *           be either 'full' or 'noAcl'.
     * }
     * @return array
     */
    public function getInfo(array $options = [])
    {
        if (!$this->data || isset($options['force'])) {
            $this->data = $this->connection->getBucket($options + $this->identity);
        }

        return $this->data;
    }

    /**
     * Retrieves the bucket's name.
     *
     * Example:
     * ```
     * $name $bucket->getName();
     * echo $name;
     * ```
     *
     * @return string
     */
    public function getName()
    {
        return $this->identity['bucket'];
    }
}
