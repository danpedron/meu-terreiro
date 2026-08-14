<?php
declare(strict_types=1);

final class LogoStorage
{
    private const MAX_BYTES = 2097152;
    private const MAX_DIMENSION = 3000;
    private const PUBLIC_PREFIX = 'assets/uploads/tenant-logos/';

    /** @return array{path:string,mime:string}|null */
    public static function store(?array $upload): ?array
    {
        if (!$upload || (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            return null;
        }
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber o logotipo enviado.');
        }
        $tmpName = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('O logotipo deve ser uma imagem de até 2 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Envie o logotipo em formato JPG, PNG ou WebP.');
        }
        $dimensions = @getimagesize($tmpName);
        if (!$dimensions || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1 || (int) $dimensions[0] > self::MAX_DIMENSION || (int) $dimensions[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('O logotipo possui dimensões inválidas ou muito grandes.');
        }

        $directory = __DIR__ . '/../public/' . self::PUBLIC_PREFIX;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento do logotipo.');
        }
        $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        $destination = $directory . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Não foi possível armazenar o logotipo.');
        }
        @chmod($destination, 0640);
        return ['path' => self::PUBLIC_PREFIX . $filename, 'mime' => $mime];
    }

    public static function delete(?string $publicPath): void
    {
        if (!self::isManagedPath($publicPath)) {
            return;
        }
        $fullPath = __DIR__ . '/../public/' . $publicPath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    public static function isManagedPath(?string $publicPath): bool
    {
        return is_string($publicPath) && preg_match('#^' . preg_quote(self::PUBLIC_PREFIX, '#') . '[a-f0-9]{48}\.(?:jpg|png|webp)$#', $publicPath) === 1;
    }
}

function tenant_logo_url(?string $publicPath): ?string
{
    return LogoStorage::isManagedPath($publicPath) ? $publicPath : null;
}
