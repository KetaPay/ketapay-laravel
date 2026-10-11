<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\Support;

use KetaPay\Http\Request;
use KetaPay\Http\Response;
use KetaPay\Http\Transport;

/** Answers every request with the same JSON and records what was sent. */
final class FakeTransport implements Transport
{
    /** @var list<Request> */
    public array $requests = [];

    /** @param array<string, mixed> $body */
    public function __construct(private readonly array $body = [], private readonly int $status = 200) {}

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        return new Response($this->status, ['ketapay-request-id' => 'req_fake'], json_encode($this->body, JSON_THROW_ON_ERROR));
    }
}
