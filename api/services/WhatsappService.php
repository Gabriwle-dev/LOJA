<?php

class WhatsappService {
    public static function enviar($telefone, $mensagem) {
        $evolution_url = "http://localhost:8080"; 
        $instance_name = "InstanciaPudim"; // Use o mesmo nome que criou no manager
        $apikey        = "LojaPudimFidelidade2026Token"; // Sua chave do .env

        $url_envio = $evolution_url . "/message/sendText/" . $instance_name;
        
        $payload = json_encode([
            "number" => "55" . preg_replace('/[^0-9]/', '', $telefone),
            "text" => $mensagem,
            "delay" => 1200, 
            "linkPreview" => false
        ]);

        $ch = curl_init($url_envio);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "apikey: " . $apikey
        ]);

        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
}
