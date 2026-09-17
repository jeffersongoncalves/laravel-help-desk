<?php

namespace JeffersonGoncalves\HelpDesk\Http\Requests\Api;

use Illuminate\Validation\Validator;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

/**
 * A file arrives base64 encoded inside the JSON body.
 *
 * ponytail: inline rather than multipart, so the signature scheme is unchanged
 * — the body is still bytes to be hashed. The ceiling is the cost: the whole
 * file is held in memory on both sides and grows by a third in transit, which
 * is why help-desk.api.max_inline_attachment exists and is smaller than
 * max_file_size. If someone needs to attach a video, the upgrade is a
 * short-lived signed upload URL and a confirm call, not a bigger cap.
 */
class StoreAttachmentRequest extends SignedApiRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'contents' => ['required', 'string'],
            'comment_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * The limits are enforced here, not only in the client. A satellite is not
     * a trust boundary: whatever it checked before sending, the central
     * application checks again.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            $decoded = $this->decoded();

            if ($decoded === null) {
                $validator->errors()->add('contents', 'The contents field must be valid base64.');

                return;
            }

            $extension = pathinfo((string) $this->input('file_name'), PATHINFO_EXTENSION);

            if (! HelpDesk::attachments()->isAllowedExtension($extension)) {
                $validator->errors()->add('file_name', 'This file type is not allowed.');
            }

            $sizeInKb = (int) ceil(strlen($decoded) / 1024);

            if (! HelpDesk::attachments()->isWithinSizeLimit($sizeInKb)) {
                $validator->errors()->add('contents', 'This file is larger than the help desk accepts.');
            }

            if ($sizeInKb > $this->inlineLimit()) {
                $validator->errors()->add('contents', "A file sent inline may be at most {$this->inlineLimit()} KB.");
            }
        });
    }

    /**
     * Strict decoding, so padding or stray characters are a rejection rather
     * than silently truncated bytes written to disk.
     */
    public function decoded(): ?string
    {
        $decoded = base64_decode((string) $this->input('contents'), true);

        return $decoded === false ? null : $decoded;
    }

    public function inlineLimit(): int
    {
        $limit = config('help-desk.api.max_inline_attachment', 2048);

        return is_numeric($limit) ? (int) $limit : 2048;
    }
}
