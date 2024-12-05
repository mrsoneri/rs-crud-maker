<?php

namespace RsCrud\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ExceptionHandlerTrait
{
    /**
     * @param  mixed  $exception
     * @return string
     */
    public function createExceptionResponse($exception)
    {
        if ($exception instanceof IlluminateValidationException) {
            $exception = new ValidationException($exception->validator->getMessageBag()->all());
        } elseif ($exception instanceof NotFoundHttpException) {
            $exception = new NotFoundException('That route does not exist');
        } elseif ($exception instanceof ModelNotFoundException) {
            $exception = new NotFoundException('That resource does not exist');
        }

        return response()->json([
            'code' => ($exception->getCode() == 0 ? 500 : $exception->getCode()),
            'type' => class_basename($exception),
            'message' => $exception->getMessage(),
            'content' => method_exists($exception, 'getContent') ? $exception->getContent() : null,
        ], ($exception->getCode() == 0 ? 500 : $exception->getCode()));
    }
}
