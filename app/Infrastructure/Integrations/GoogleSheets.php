<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations;

/**
 * Google OAuth plus the two Sheets calls the export needs: create a spreadsheet, append rows.
 * The drive.file scope only reaches files this app created, never the rest of the user's Drive.
 */
class GoogleSheets extends OAuthProvider
{
    private const API = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function name(): string
    {
        return 'Google';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/drive.file', 'openid', 'email'];
    }

    protected function extraAuthorizeParams(): array
    {
        // offline + consent: Google only hands out a refresh token this way
        return ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'];
    }

    /** @return array{id: string, url: string, sheet: string} */
    public function createSpreadsheet(string $token, string $title, string $sheet): array
    {
        $response = $this->http->withToken($token)->timeout(30)->post(self::API, [
            'properties' => ['title' => $title],
            'sheets' => [['properties' => ['title' => $sheet, 'gridProperties' => ['frozenRowCount' => 1]]]],
        ])->throw();

        return [
            'id' => (string) $response->json('spreadsheetId'),
            'url' => (string) $response->json('spreadsheetUrl'),
            'sheet' => $sheet,
        ];
    }

    /** @param  list<list<string|float|int|null>>  $rows */
    public function append(string $token, string $spreadsheetId, string $sheet, array $rows): void
    {
        $range = rawurlencode("'{$sheet}'!A1");
        $this->http->withToken($token)->timeout(30)
            ->post(self::API."/{$spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS", [
                'majorDimension' => 'ROWS',
                'values' => $rows,
            ])->throw();
    }
}
