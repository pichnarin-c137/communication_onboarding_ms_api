<?php

namespace App\Services\Telegram;

use InvalidArgumentException;

class TelegramMessageTemplate
{
    /**
     * Render a message template for the given type and language.
     *
     * @param  string  $messageType  One of the supported message type keys (e.g. 'training_scheduled')
     * @param  string  $language  Language code (e.g. 'en', 'km'). Falls back to 'en' if unsupported.
     * @param  array  $variables  Named placeholder values, keyed without the colon (e.g. ['client_name' => 'Sokha'])
     * @return string The rendered message string
     *
     * @throws InvalidArgumentException if the message type key does not exist in the template file
     */
    public function render(string $messageType, string $language, array $variables): string
    {
        $supportedLanguages = $this->supportedLanguages();

        if (! in_array($language, $supportedLanguages, true)) {
            $language = 'en';
        }

        $templates = $this->loadTemplates($language);

        if (! array_key_exists($messageType, $templates)) {
            throw new InvalidArgumentException(
                "Telegram message type '$messageType' does not exist in the '$language' template file."
            );
        }

        $template = $templates[$messageType];

        foreach ($variables as $key => $value) {
            $template = str_replace(":$key", (string) $value, $template);
        }

        return $template;
    }

    /**
     * Return the list of supported language codes.
     */
    public function supportedLanguages(): array
    {
        return config('coms.telegram_supported_languages', ['en', 'km']);
    }

    /**
     * Load the template array for the given language.
     */
    private function loadTemplates(string $language): array
    {
        $path = lang_path("$language/telegram.php");

        if (! file_exists($path)) {
            // Fall back to English if the file is somehow missing
            $path = lang_path('en/telegram.php');
        }

        return require $path;
    }
}
