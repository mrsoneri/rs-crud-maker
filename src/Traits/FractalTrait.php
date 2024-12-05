<?php

namespace RsCrud\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait FractalTrait
{
    public function emptyResponse()
    {
        return response()->json([]);
    }

    public function simpleResponse(array $data = [])
    {
        return response()->json($data);
    }

    protected function jsonResponse(bool $success, int $code, $data = null, ?array $error = null, string $messageKey = '', ?array $pagination = null, ...$params): JsonResponse
    {
        $response = $this->createTransformResponseArray($success, $code, $data, $error, $messageKey, $pagination, ...$params);

        return response()->json($response, $code);
    }

    protected function createTransformResponseArray(bool $success, int $code, $data = null, ?array $error = null, string $messageKey = '', $pagination = [], ...$params): array
    {
        $response = [
            'success' => $success,
            'code' => $code,
        ];
        if (! empty($messageKey)) {
            $response['message'] = $messageKey;
        }
        if (! empty($data)) {
            $response['data'] = $data;
        }

        if (! empty($error)) {
            $response['errors'] = $error;
        }

        if (! empty($pagination)) {
            $response['pagination'] = $pagination;
        }

        // Run this loop only if $extraParams is not empty
        if (! empty($params)) {
            foreach ($params as $param) {
                if (is_array($param)) {
                    $response = array_merge($response, $param);
                }
            }
        }

        return $response;
    }

    /**
     * Handle and return a JSON response for exceptions.
     *
     * This method is used to return a generic error response when an exception
     * is caught. The message is translated using the 'common' prefix to indicate
     * that something went wrong, with a status code of 500 (Internal Server Error).
     *
     * @param  \Exception  $e  The exception that was caught.
     * @return \Illuminate\Http\JsonResponse The JSON error response.
     */
    protected function handleException(\Exception $e)
    {
        return $this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], 'something went wrong');
    }
}
