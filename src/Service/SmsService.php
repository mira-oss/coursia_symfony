<?php

namespace App\Service;

use AfricasTalking\SDK\AfricasTalking;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SmsService
{
    private AfricasTalking $at;
    private string $senderId;
    private string $appUrl;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%env(AT_USERNAME)%')] string $username,
        #[Autowire('%env(AT_API_KEY)%')] string $apiKey,
        #[Autowire('%env(AT_SENDER_ID)%')] string $senderId,
        #[Autowire('%env(APP_URL)%')] string $appUrl,
        LoggerInterface $logger
    ) {
        $this->at = new AfricasTalking($username, $apiKey);
        $this->senderId = $senderId;
        $this->appUrl = $appUrl;
        $this->logger = $logger;
    }

    /**
     * Envoie le lien de confirmation de livraison au destinataire (cours régionale)
     */
    public function sendDeliveryConfirmationLink(
        string $phone,
        string $recipientFirstName,
        string $senderName,
        string $deliveryToken
    ): bool {
        $link = $this->appUrl . '/confirm/' . $deliveryToken;

        $message = "Bonjour {$recipientFirstName}, votre colis de {$senderName} est en route. "
            . "Quand vous le recevrez, cliquez sur ce lien pour confirmer la reception : {$link}";

        return $this->send($phone, $message);
    }

    /**
     * Envoie le code de réinitialisation de mot de passe par SMS
     */
    public function sendPasswordResetCode(string $phone, string $code): bool
    {
        $message = "Coursia Admin - Votre code de réinitialisation est : {$code}. Valable 1 heure. Ne le partagez pas.";
        return $this->send($phone, $message);
    }

    /**
     * Envoie un SMS générique
     */
    public function send(string $phone, string $message): bool
    {
        try {
            $sms = $this->at->sms();
            $result = $sms->send([
                'to'      => $phone,
                'message' => $message,
                'from'    => $this->senderId,
            ]);

            $this->logger->info('SMS envoyé', [
                'to'     => $phone,
                'result' => $result,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi SMS', [
                'to'      => $phone,
                'message' => $message,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }
}
