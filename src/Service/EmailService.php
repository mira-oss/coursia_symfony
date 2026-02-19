<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Service pour l'envoi d'emails
 * Pour l'instant, simule l'envoi en loguant (à remplacer par un vrai mailer)
 */
class EmailService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Envoie l'email de bienvenue à un nouveau Chevalier
     * TODO: Intégrer Symfony Mailer quand configuré
     */
    public function sendChevalierWelcomeEmail(User $chevalier, string $temporaryPassword): void
    {
        $email = $chevalier->getEmail();
        $firstName = $chevalier->getFirstName();

        // Pour l'instant, on logue l'email (en production, utiliser Symfony Mailer)
        $this->logger->info('📧 Email envoyé à un nouveau Chevalier', [
            'to' => $email,
            'subject' => 'Bienvenue chez Coursia',
            'temporaryPassword' => $temporaryPassword,
            'fullMessage' => $this->renderTextVersion($chevalier, $temporaryPassword)
        ]);

        // En production, décommenter :
        // $email = (new Email())
        //     ->from('noreply@coursia.app')
        //     ->to($chevalier->getEmail())
        //     ->subject('Bienvenue chez Coursia - Votre compte est activé ✅')
    }

    /**
     * Template HTML pour l'email de bienvenue Chevalier
     */
    private function renderChevalierWelcomeTemplate(User $chevalier, string $temporaryPassword): string
    {
        $firstName = htmlspecialchars($chevalier->getFirstName());
        $email = htmlspecialchars($chevalier->getEmail());

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #F97316 0%, #EA580C 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .credentials { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #F97316; }
                .button { display: inline-block; background: #F97316; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Bienvenue chez Coursia !</h1>
                    <p>Vous êtes maintenant un Chevalier Vérifié</p>
                </div>
                <div class="content">
                    <p>Bonjour <strong>{$firstName}</strong>,</p>
                    
                    <p>Félicitations ! Votre candidature a été approuvée après vérification de votre identité. Vous pouvez maintenant commencer à publier vos trajets et gagner de l'argent en aidant les gens à envoyer leurs colis.</p>
                    
                    <div class="credentials">
                        <h3>🔐 Vos Identifiants de Connexion</h3>
                        <p><strong>Email :</strong> {$email}</p>
                        <p><strong>Mot de passe temporaire :</strong> <code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px; font-size: 16px;">{$temporaryPassword}</code></p>
                    </div>
                    
                    <div class="warning">
                        <strong>⚠️ IMPORTANT :</strong> Pour des raisons de sécurité, vous devrez changer ce mot de passe lors de votre première connexion.
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="#" class="button">Télécharger l'Application</a>
                    </p>
                    
                    <h3>📱 Prochaines Étapes</h3>
                    <ol>
                        <li>Téléchargez l'application Coursia</li>
                        <li>Connectez-vous avec les identifiants ci-dessus</li>
                        <li>Changez votre mot de passe</li>
                        <li>Publiez votre premier trajet</li>
                    </ol>
                    
                    <p>Si vous avez des questions, n'hésitez pas à nous contacter à support@coursia.app</p>
                    
                    <p>Bienvenue dans la famille Coursia ! 🚀</p>
                    
                    <p>Cordialement,<br><strong>L'équipe Coursia</strong></p>
                </div>
                <div class="footer">
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>&copy; 2026 Coursia - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
