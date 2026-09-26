<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

abstract class DataFormRequest extends FormRequest
{
    /**
     * Validate data submitted outside the traditional controller pipeline.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validatePayload(array $payload, ?User $user = null): array
    {
        $this->replace($payload);

        if ($user) {
            $this->setUserResolver(fn (): User => $user);
        }

        $validator = ValidatorFacade::make(
            $payload,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        foreach ($this->after() as $callback) {
            $validator->after($callback);
        }

        return $validator->validate();
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [];
    }
}
