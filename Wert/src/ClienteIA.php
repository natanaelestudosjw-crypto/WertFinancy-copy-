<?php
final class ClienteIA {
    private const MODEL = 'gemini-3.8-flash'; // confirme o ID no AI Studio

    // AJUSTE: caminho do .env FORA da pasta pública (fora do htdocs/www)
    private const ENV_PATH = 'C:/xampp/segredos/wert.env';

    public function analisar(array $dados): string {
        $key = $this->chave();
        if ($key === '') {
            throw new RuntimeException('Configuração da IA indisponível.');
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' =>
                'Você é um assistente financeiro gentil. Responda em português, curto, sem julgar. '
                . 'Use somente os números fornecidos; não faça contas, não invente números e não '
                . 'recomende investimentos específicos. Dê no máximo 3 sugestões realistas.'
            ]]],
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => 'Dados agregados: ' . json_encode($dados, JSON_UNESCAPED_UNICODE)]],
            ]],
            'generationConfig' => ['maxOutputTokens' => 800, 'temperature' => 0.4],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent';

        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw  = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        if ($raw === false) throw new RuntimeException('Não foi possível conectar ao assistente agora.');
        if ($code === 429)  throw new RuntimeException('O limite do assistente foi atingido. Tente novamente mais tarde.');
        if ($code < 200 || $code >= 300) throw new RuntimeException('O assistente está indisponível no momento.');

        $text = json_decode($raw, true)['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Não foi possível gerar uma análise agora.');
        }
        return mb_substr(trim($text), 0, 2500);
    }

    private function chave(): string {
        $f = self::ENV_PATH;
        if (!is_readable($f)) return '';
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            $l = trim($l);
            if (str_starts_with($l, 'GEMINI_API_KEY=')) {
                return trim(substr($l, 15), " \t\"'");
            }
        }
        return '';
    }
}