<?php

namespace App\Enums;

enum VideoErrorCode: string
{
    case InvalidInput = 'INVALID_INPUT';
    case SafetyBlocked = 'SAFETY_BLOCKED';
    case QuotaExceeded = 'QUOTA_EXCEEDED';
    case RateLimited = 'RATE_LIMITED';
    case UpstreamError = 'UPSTREAM_ERROR';
    case Timeout = 'TIMEOUT';
    case Internal = 'INTERNAL';

    /**
     * Transient errors that are worth another attempt.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::QuotaExceeded, self::UpstreamError, self::Timeout], true);
    }

    public function message(): string
    {
        return match ($this) {
            self::InvalidInput => 'Input tidak valid. Periksa prompt dan gambar Anda.',
            self::SafetyBlocked => 'Permintaan diblokir oleh filter keamanan. Ubah prompt atau gambar Anda.',
            self::QuotaExceeded => 'Kuota layanan sedang penuh. Silakan coba lagi beberapa saat lagi.',
            self::RateLimited => 'Terlalu banyak permintaan. Silakan tunggu sebentar.',
            self::UpstreamError => 'Layanan AI sedang bermasalah. Silakan coba lagi.',
            self::Timeout => 'Proses pembuatan video melewati batas waktu. Silakan coba lagi.',
            self::Internal => 'Terjadi kesalahan internal. Silakan coba lagi.',
        };
    }
}
