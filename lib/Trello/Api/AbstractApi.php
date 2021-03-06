<?php

namespace Trello\Api;

use Trello\Client;
use Trello\HttpClient\Message\ResponseMediator;
use Trello\Exception\InvalidArgumentException;
use Trello\Exception\BadMethodCallException;
use Trello\Exception\MissingArgumentException;

/**
 * Abstract class for Api classes
 *
 * @author Christian Daguerre <christian.daguerre@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
abstract class AbstractApi implements ApiInterface
{
    /**
     * API sub namespace
     *
     * @var string
     */
    protected $path;

    /**
     * The client
     *
     * @var Client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Catches any undefined "get{$field}" calls, and passes them
     * to the getField() if the $field is in the $this->fields property
     *
     * @param string $method    called method
     * @param array  $arguments array of arguments passed to called method
     *
     * @return array
     *
     * @throws BadMethodCallException If the method does not start with "get"
     *                                or the field is not included in the $fields property
     */
    public function __call($method, $arguments)
    {
        if (isset($this->fields) && substr($method, 0, 3) === 'get') {
            $property = lcfirst(substr($method, 3));
            if (in_array($property, $this->fields)) {
                return $this->getField(reset($arguments));
            }
        }

        throw new BadMethodCallException(sprintf(
            'There is no method named "%s" in class "%s".',
            $method,
            get_called_class()
        ));
    }

    /**
     * Get field names (properties)
     *
     * @return array array of fields
     */
    public function getFields()
    {
        return static::$fields;
    }

    /**
     * Get a field value by field name
     *
     * @param string $id    the board's id
     * @param string $field the field
     *
     * @return mixed field value
     *
     * @throws InvalidArgumentException If the field does not exist
     */
    public function getField($id, $field)
    {
        if (!in_array($field, static::$fields)) {
            throw new InvalidArgumentException(sprintf('There is no field named %s.', $field));
        }

        $response = $this->get($this->path.'/'.rawurlencode($id).'/'.rawurlencode($field));

        return isset($response['_value']) ? $response['_value'] : $response;
    }

    /**
     * Send a GET request with query parameters.
     *
     * @param string $path           Request path.
     * @param array  $parameters     GET parameters.
     * @param array  $requestHeaders Request Headers.
     *
     * @return \Guzzle\Http\EntityBodyInterface|mixed|string
     */
    protected function get($path, array $parameters = array(), $requestHeaders = array())
    {
        $response = $this->client->getHttpClient()->get($path, $parameters, $requestHeaders);

        return ResponseMediator::getContent($response);
    }

    /**
     * Send a HEAD request with query parameters
     *
     * @param string $path           Request path.
     * @param array  $parameters     HEAD parameters.
     * @param array  $requestHeaders Request headers.
     *
     * @return \Guzzle\Http\Message\Response
     */
    protected function head($path, array $parameters = array(), $requestHeaders = array())
    {
        $response = $this->client->getHttpClient()->request($path, null, 'HEAD', $requestHeaders, array(
            'query' => $parameters,
        ));

        return $response;
    }

    /**
     * Send a POST request with JSON-encoded parameters.
     *
     * @param string $path           Request path.
     * @param array  $parameters     POST parameters to be JSON encoded.
     * @param array  $requestHeaders Request headers.
     *
     * @return mixed
     */
    protected function post($path, array $parameters = array(), $requestHeaders = array())
    {
        return $this->postRaw(
            $path,
            $this->createParametersBody($parameters),
            $requestHeaders
        );
    }

    /**
     * Send a POST request with raw data.
     *
     * @param string $path           Request path.
     * @param mixed  $body           Request body.
     * @param array  $requestHeaders Request headers.
     *
     * @return \Guzzle\Http\EntityBodyInterface|mixed|string
     */
    protected function postRaw($path, $body, $requestHeaders = array())
    {
        $response = $this->client->getHttpClient()->post(
            $path,
            $body,
            $requestHeaders
        );

        return ResponseMediator::getContent($response);
    }

    /**
     * Send a PATCH request with JSON-encoded parameters.
     *
     * @param string $path           Request path.
     * @param array  $parameters     POST parameters to be JSON encoded.
     * @param array  $requestHeaders Request headers.
     *
     * @return mixed
     */
    protected function patch($path, array $parameters = array(), $requestHeaders = array())
    {
        $response = $this->client->getHttpClient()->patch(
            $path,
            $this->createParametersBody($parameters),
            $requestHeaders
        );

        return ResponseMediator::getContent($response);
    }

    /**
     * Send a PUT request with JSON-encoded parameters.
     *
     * @param string $path           Request path.
     * @param array  $parameters     POST parameters to be JSON encoded.
     * @param array  $requestHeaders Request headers.
     *
     * @return mixed
     */
    protected function put($path, array $parameters = array(), $requestHeaders = array())
    {
        foreach ($parameters as $name => $parameter) {
            if (is_bool($parameter)) {
                $parameters[$name] = $parameter ? 'true' : 'false';
            }
        }

        $response = $this->client->getHttpClient()->put(
            $path,
            $this->createParametersBody($parameters),
            $requestHeaders
        );

        return ResponseMediator::getContent($response);
    }

    /**
     * Send a DELETE request with JSON-encoded parameters.
     *
     * @param string $path           Request path.
     * @param array  $parameters     POST parameters to be JSON encoded.
     * @param array  $requestHeaders Request headers.
     *
     * @return mixed
     */
    protected function delete($path, array $parameters = array(), $requestHeaders = array())
    {
        $response = $this->client->getHttpClient()->delete(
            $path,
            $this->createParametersBody($parameters),
            $requestHeaders
        );

        return ResponseMediator::getContent($response);
    }

    /**
     * Prepare request parameters.
     *
     * @param array $parameters Request parameters
     *
     * @return null|string
     */
    protected function createParametersBody(array $parameters)
    {
        foreach ($parameters as $name => $parameter) {
            if (is_bool($parameter)) {
                $parameters[$name] = $parameter ? 'true' : 'false';
            } elseif (is_array($parameter)) {
                foreach ($parameter as $subName => $subParameter) {
                    if (is_bool($subParameter)) {
                        $subParameter = $subParameter ? 'true' : 'false';
                    }
                    $parameters[$name.'/'.$subName] = $subParameter;
                }
                unset($parameters[$name]);
            }
        }

        return $parameters;
    }

    /**
     * Validate parameters array
     *
     * @param array $required required properties (array keys)
     * @param array $params   array to check for existence of the required keys
     *
     * @throws MissingArgumentException if a required parameter is missing
     */
    protected function validateParams(array $required, array $params)
    {
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                throw new MissingArgumentException(sprintf('The "%s" parameter is required.', $param));
            }
        }
    }

    protected function validateFilters(array $allowed, $filters)
    {
        if (!is_array($filters)) {
            $filters = array($filters);
        }

        foreach ($filters as $filter) {
            if (!in_array($filter, $allowed)) {
                throw new InvalidArgumentException(sprintf(
                    'The "filter" parameter may contain only values within "%s". "%s" given.',
                    implode(", ", $allowed),
                    $filter
                ));
            }
        }

        return $filters;
    }

    protected function validateFields(array $allowed, $fields)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        foreach ($fields as $field) {
            if (!in_array($field, $allowed)) {
                throw new InvalidArgumentException(sprintf(
                    'The "field" parameter may contain only values within "%s". "%s" given.',
                    implode(", ", $allowed),
                    $field
                ));
            }
        }

        return $fields;
    }

    protected function validateAtLeastOneOf(array $atLeastOneOf, array $params)
    {
        foreach ($atLeastOneOf as $param) {
            if (isset($params[$param])) {
                $foundOne = true;
            }
        }

        if (!isset($foundOne)) {
            throw new MissingArgumentException(sprintf(
                'You need to provide at least one of the following parameters "%s".',
                implode('", "', $atLeastOneOf)
            ));
        }

        return true;
    }

    protected function getPath($id = null)
    {
        if ($id) {
            return preg_replace('/\#id\#/', $id, $this->path);
        }

        return $this->path;
    }
}
