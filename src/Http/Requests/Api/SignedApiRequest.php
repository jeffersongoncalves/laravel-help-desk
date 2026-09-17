<?php

namespace JeffersonGoncalves\HelpDesk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JeffersonGoncalves\HelpDesk\Api\RemoteActor;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyHelpDeskSignature;

/**
 * Everything a satellite sends on behalf of a person.
 *
 * The app key is read from the request attribute the signature middleware set,
 * never from the payload. A satellite putting someone else's key in the body
 * changes nothing.
 */
abstract class SignedApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'actor.type' => ['required', 'string', 'max:255'],
            'actor.id' => ['required'],
            'actor.name' => ['nullable', 'string', 'max:255'],
            'actor.email' => ['nullable', 'email', 'max:255'],
        ], $this->payloadRules());
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function payloadRules(): array;

    /**
     * An application may only act as one of its own actor types.
     *
     * Without this a satellite could open a ticket that appears to come from
     * another application, which would defeat both the per-application morph
     * alias and the scoping the central application does by app key.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = config("help-desk.api.clients.{$this->appKey()}.actor_types", []);
            $allowed = is_array($allowed) ? $allowed : [];

            if (! in_array($this->input('actor.type'), $allowed, true)) {
                $validator->errors()->add('actor.type', 'This actor type is not registered for this application.');
            }
        });
    }

    public function appKey(): string
    {
        return (string) $this->attributes->get(VerifyHelpDeskSignature::ATTRIBUTE);
    }

    public function actor(): RemoteActor
    {
        /** @var array{type: string, id: int|string, name?: string|null, email?: string|null} $actor */
        $actor = $this->validated()['actor'];

        return RemoteActor::fromArray($actor);
    }
}
