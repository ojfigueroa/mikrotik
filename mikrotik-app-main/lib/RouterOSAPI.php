<?php
// lib/RouterOSAPI.php

class RouterOSAPI {
    private $socket    = null;
    private $connected = false;

    public function connect(string $host, int $port = 8728, int $timeout = 10): bool {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            throw new Exception("Router no alcanzable: $errstr (Error $errno)");
        }
        stream_set_timeout($this->socket, $timeout);
        $this->connected = true;
        return true;
    }

    public function login(string $user, string $password): bool {
        if (!$this->connected) {
            throw new Exception("No hay conexión activa.");
        }

        // Solicitar challenge (RouterOS v6)
        $this->sendWord('/login');
        $this->sendWord('');

        $challenge = null;
        while (true) {
            $word = $this->readWord();
            if ($word === null) break;
            if ($word === '!done') break;
            if (str_starts_with($word, '=ret=')) {
                $challenge = substr($word, 5);
            }
        }

        if ($challenge !== null) {
            // v6: MD5 challenge
            $hash = md5(chr(0) . $password . pack('H*', $challenge));
            $this->sendWord('/login');
            $this->sendWord('=name=' . $user);
            $this->sendWord('=response=00' . $hash);
            $this->sendWord('');
        } else {
            // v7: login directo
            $this->sendWord('/login');
            $this->sendWord('=name=' . $user);
            $this->sendWord('=password=' . $password);
            $this->sendWord('');
        }

        while (true) {
            $word = $this->readWord();
            if ($word === null) break;
            if ($word === '!done') return true;
            if (str_starts_with($word, '!trap')) {
                throw new Exception("Autenticación fallida. Usuario o contraseña incorrectos.");
            }
        }

        return false;
    }

    public function communicate(array $command): array {
        foreach ($command as $word) {
            $this->sendWord($word);
        }
        $this->sendWord('');
        return $this->readSentence();
    }

    private function sendWord(string $word): void {
        $len = strlen($word);
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            $len |= 0xC0000000;
            fwrite($this->socket,
                chr(($len >> 24) & 0xFF) .
                chr(($len >> 16) & 0xFF) .
                chr(($len >> 8)  & 0xFF) .
                chr( $len        & 0xFF)
            );
        }
        if (strlen($word) > 0) {
            fwrite($this->socket, $word);
        }
    }

    private function readWord(): ?string {
        $first = fread($this->socket, 1);
        if ($first === false || $first === '') return null;

        $b = ord($first);
        if ($b === 0) return '';

        if (($b & 0x80) === 0) {
            $len = $b;
        } elseif (($b & 0xC0) === 0x80) {
            $b2  = ord(fread($this->socket, 1));
            $len = (($b & 0x3F) << 8) | $b2;
        } elseif (($b & 0xE0) === 0xC0) {
            $d   = fread($this->socket, 2);
            $len = (($b & 0x1F) << 16) | (ord($d[0]) << 8) | ord($d[1]);
        } else {
            $d   = fread($this->socket, 3);
            $len = (($b & 0x0F) << 24) | (ord($d[0]) << 16) | (ord($d[1]) << 8) | ord($d[2]);
        }

        $word = '';
        $left = $len;
        while ($left > 0) {
            $chunk = fread($this->socket, $left);
            if ($chunk === false || $chunk === '') break;
            $word .= $chunk;
            $left -= strlen($chunk);
        }
        return $word;
    }

    private function readSentence(): array {
        $result = [];
        while (true) {
            $word = $this->readWord();
            if ($word === null || $word === '') {
                if (in_array('!done', $result)) break;
                foreach ($result as $r) {
                    if (str_starts_with($r, '!trap') || str_starts_with($r, '!fatal')) break 2;
                }
                continue;
            }
            $result[] = $word;
        }
        return $result;
    }

    public function disconnect(): void {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket    = null;
            $this->connected = false;
        }
    }

    public function isConnected(): bool {
        return $this->connected;
    }
}