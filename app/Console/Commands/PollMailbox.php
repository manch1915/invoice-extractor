<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentSource;
use App\Services\Documents\DocumentIngestor;
use App\Services\Documents\UnsupportedFileException;
use Illuminate\Console\Command;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

/**
 * Picks up attachments from unread emails in the collection mailbox, so suppliers' invoices
 * can simply be forwarded. Messages are marked as read once their attachments are taken.
 */
class PollMailbox extends Command
{
    protected $signature = 'mailbox:poll {--limit=25 : messages per run}';

    protected $description = 'Import invoice attachments from the IMAP mailbox';

    public function handle(DocumentIngestor $ingestor): int
    {
        $config = (array) config('invoices.mailbox');
        if (empty($config['host'])) {
            $this->line('Mailbox is not configured.');

            return self::SUCCESS;
        }

        $client = (new ClientManager)->make([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'validate_cert' => true,
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
        ]);
        $client->connect();

        $allowed = (array) config('invoices.upload.mimes');
        $maxBytes = (int) config('invoices.upload.max_kb') * 1024;
        $messages = $client->getFolderByPath((string) $config['folder'])?->messages()->unseen()->limit((int) $this->option('limit'))->get() ?? [];

        $imported = 0;
        foreach ($messages as $message) {
            $meta = [
                'from' => (string) $message->getFrom()->first()?->mail,
                'subject' => mb_substr((string) $message->getSubject(), 0, 200),
            ];
            foreach ($message->getAttachments() as $attachment) {
                $name = (string) $attachment->getName();
                $content = (string) $attachment->getContent();
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($extension, $allowed, true) || $content === '' || strlen($content) > $maxBytes) {
                    continue;
                }
                try {
                    $ingestor->ingest($content, $name, DocumentSource::Email, null, $meta);
                    $imported++;
                } catch (UnsupportedFileException $e) {
                    $this->warn($e->getMessage());
                } catch (Throwable $e) {
                    report($e);
                }
            }
            $message->setFlag('Seen');
        }

        $this->info("Imported {$imported} attachments.");

        return self::SUCCESS;
    }
}
