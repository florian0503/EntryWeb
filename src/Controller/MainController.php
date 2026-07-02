<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\NewsletterSubscriber;
use App\Entity\Parrainage;
use App\Entity\Realisation;
use App\Repository\CategoryRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\ParrainageRepository;
use App\Repository\RealisationRepository;
use App\Service\LeadMagnetPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(Request $request, RealisationRepository $realisationRepository): Response
    {
        $ref = $request->query->getString('ref');
        if ('' !== $ref) {
            $request->getSession()->set('referral_code', $ref);
        }

        $realisations = $realisationRepository->findPublished();

        return $this->render('pages/home.html.twig', [
            'realisations' => $realisations,
            'realisationsCount' => count($realisations),
        ]);
    }

    #[Route('/offres', name: 'app_offers')]
    public function offers(RealisationRepository $realisationRepository): Response
    {
        return $this->render('pages/offers.html.twig', [
            'realisationsCount' => count($realisationRepository->findPublished()),
        ]);
    }

    #[Route('/realisations', name: 'app_portfolio')]
    public function portfolio(RealisationRepository $realisationRepository, CategoryRepository $categoryRepository): Response
    {
        $realisations = $realisationRepository->findPublished();
        $categories = $categoryRepository->findAllOrderedByName();

        return $this->render('pages/portfolio.html.twig', [
            'realisations' => $realisations,
            'categories' => $categories,
        ]);
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(RealisationRepository $realisationRepository): Response
    {
        $realisationsCount = count($realisationRepository->findPublished());

        return $this->render('pages/about.html.twig', [
            'realisationsCount' => $realisationsCount,
        ]);
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        ParrainageRepository $parrainageRepository,
        #[Autowire('%env(CONTACT_EMAIL)%')]
        string $contactEmail = 'contact@entryweb.fr',
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_contact');
            }

            $name = $request->request->getString('name');
            $clientEmail = $request->request->getString('email');
            $budget = $request->request->getString('budget');
            $subscription = $request->request->getString('subscription');
            $message = $request->request->getString('message');
            $referralCode = $request->request->getString('referral_code');

            if (false === filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse email invalide.');

                return $this->redirectToRoute('app_contact');
            }

            // Find referral sponsor
            $parrainEmail = '';
            if ('' !== $referralCode) {
                $parrain = $parrainageRepository->findByReferralCode($referralCode);
                if (null !== $parrain) {
                    $parrainEmail = $parrain->getEmail();
                    $parrain->setReferralsCount($parrain->getReferralsCount() + 1);
                }
            }

            $contactMessage = new ContactMessage();
            $contactMessage->setName($name);
            $contactMessage->setEmail($clientEmail);
            $contactMessage->setProjectType($budget);
            $contactMessage->setBudget($budget);
            $contactMessage->setMessage($message);
            if ('' !== $parrainEmail) {
                $contactMessage->setReferredBy($parrainEmail);
            }

            $entityManager->persist($contactMessage);
            $entityManager->flush();

            $parrainRow = '';
            $parrainText = '';
            if ('' !== $parrainEmail) {
                $parrainRow = <<<HTML
                        <tr>
                            <td style="padding: 12px 16px; background: #ecfdf5; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #065f46; vertical-align: top; font-weight: 600;">Parrainé par</td>
                            <td style="padding: 12px 16px; background: #ecfdf5; border-bottom: 1px solid #f0f0f0; font-size: 15px; color: #065f46;">{$parrainEmail}</td>
                        </tr>
                HTML;
                $parrainText = "Parrainé par : {$parrainEmail}\n";
            }

            $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeEmail = htmlspecialchars($clientEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeBudget = htmlspecialchars($budget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeSubscription = htmlspecialchars($subscription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

            $htmlContent = <<<HTML
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
                <div style="background: #0066ff; padding: 32px; text-align: center;">
                    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">EntryWeb</h1>
                    <p style="color: #cce0ff; margin: 8px 0 0; font-size: 14px;">Nouveau message de contact</p>
                </div>
                <div style="padding: 32px; background: #f8f9fa; border: 1px solid #e9ecef;">
                    <table style="width: 100%; border-collapse: collapse;">
                        {$parrainRow}
                        <tr>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #666; width: 120px; vertical-align: top;">Nom</td>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 15px; color: #111;">{$safeName}</td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #666; vertical-align: top;">Email</td>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 15px;"><a href="mailto:{$safeEmail}" style="color: #111; text-decoration: none;">{$safeEmail}</a></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #666; vertical-align: top;">Formule</td>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 15px; color: #111;">{$safeBudget}</td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #666; vertical-align: top;">Abonnement</td>
                            <td style="padding: 12px 16px; background: #ffffff; border-bottom: 1px solid #f0f0f0; font-size: 15px; color: #111;">{$safeSubscription}</td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 16px; background: #ffffff; font-size: 13px; color: #666; vertical-align: top;">Message</td>
                            <td style="padding: 12px 16px; background: #ffffff; font-size: 15px; color: #111; line-height: 1.6;">{$safeMessage}</td>
                        </tr>
                    </table>
                </div>
                <div style="padding: 24px; text-align: center; background: #0066ff;">
                    <a href="mailto:{$safeEmail}" style="display: inline-block; padding: 12px 32px; background: #ffffff; color: #0066ff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">Repondre a {$safeName}</a>
                    <p style="color: #cce0ff; margin: 16px 0 0; font-size: 12px;">EntryWeb - L'agence web des entrepreneurs</p>
                </div>
            </div>
            HTML;

            $email = (new Email())
                ->from($contactEmail)
                ->to($contactEmail)
                ->replyTo($clientEmail)
                ->subject('Nouveau contact - '.$name)
                ->html($htmlContent)
                ->text("{$parrainText}Nom : {$name}\nEmail : {$clientEmail}\nFormule : {$budget}\n\nMessage :\n{$message}");

            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer plus tard.');

                return $this->redirectToRoute('app_contact');
            }

            $request->getSession()->remove('referral_code');
            $this->addFlash('success', 'contact_sent');

            return $this->redirectToRoute('app_contact');
        }

        // Check if a referral code is in session
        $parrain = null;
        $referralCode = $request->getSession()->get('referral_code');
        if (null !== $referralCode) {
            $parrain = $parrainageRepository->findByReferralCode($referralCode);
        }

        $allowedOffers = ['pack-essentiel', 'pack-business', 'e-commerce', 'sur-mesure', 'autre'];
        $selectedOffer = $request->query->getString('offer');
        if (!in_array($selectedOffer, $allowedOffers, true)) {
            $selectedOffer = '';
        }

        $allowedSubscriptions = ['starter', 'confort', 'premium'];
        $selectedSubscription = $request->query->getString('subscription');
        if (!in_array($selectedSubscription, $allowedSubscriptions, true)) {
            $selectedSubscription = '';
        }

        // Calcul de la valeur exacte à pré-sélectionner dans le select
        $preselectedBudget = match (true) {
            'e-commerce' === $selectedOffer => 'e-commerce-premium',
            'sur-mesure' === $selectedOffer => 'sur-mesure',
            'autre' === $selectedOffer => 'autre',
            '' !== $selectedOffer && '' !== $selectedSubscription => $selectedOffer.'-'.$selectedSubscription,
            default => '',
        };

        return $this->render('pages/contact.html.twig', [
            'parrain' => $parrain,
            'selectedOffer' => $selectedOffer,
            'selectedSubscription' => $selectedSubscription,
            'preselectedBudget' => $preselectedBudget,
        ]);
    }

    #[Route('/newsletter/inscription', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function newsletterSubscribe(
        Request $request,
        EntityManagerInterface $entityManager,
        NewsletterSubscriberRepository $subscriberRepository,
        MailerInterface $mailer,
        LeadMagnetPdfService $pdfService,
        #[Autowire('%env(CONTACT_EMAIL)%')]
        string $contactEmail = 'contact@entryweb.fr',
    ): Response {
        $isAjax = 'XMLHttpRequest' === $request->headers->get('X-Requested-With');

        if (!$this->isCsrfTokenValid('newsletter_form', $request->request->getString('_token'))) {
            if ($isAjax) {
                return new JsonResponse(['status' => 'error', 'message' => 'Jeton invalide, veuillez réessayer.']);
            }
            $this->addFlash('newsletter_error', 'Jeton invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_home', ['_fragment' => 'newsletter']);
        }

        $email = trim($request->request->getString('email'));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isAjax) {
                return new JsonResponse(['status' => 'error', 'message' => 'Adresse email invalide.']);
            }
            $this->addFlash('newsletter_error', 'Adresse email invalide.');

            return $this->redirectToRoute('app_home', ['_fragment' => 'newsletter']);
        }

        $existing = $subscriberRepository->findByEmail($email);
        if (null !== $existing) {
            if ($isAjax) {
                return new JsonResponse([
                    'status' => 'success',
                    'title' => 'Vous êtes déjà inscrit !',
                    'message' => 'Le guide vous a déjà été envoyé. Consultez votre boîte mail.',
                ]);
            }
            $this->addFlash('newsletter_success', 'already_subscribed');

            return $this->redirectToRoute('app_home', ['_fragment' => 'newsletter']);
        }

        $subscriber = new NewsletterSubscriber();
        $subscriber->setEmail($email);
        $entityManager->persist($subscriber);

        $pdfContent = $pdfService->generate();
        $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $htmlContent = <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: #0066ff; padding: 32px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">EntryWeb</h1>
                <p style="color: #cce0ff; margin: 8px 0 0; font-size: 14px;">Votre guide gratuit est arrivé !</p>
            </div>
            <div style="padding: 40px 32px;">
                <h2 style="font-size: 20px; color: #1a1a2e; margin: 0 0 16px;">Voici votre checklist 🎉</h2>
                <p style="font-size: 15px; color: #555; line-height: 1.7; margin: 0 0 20px;">
                    Merci pour votre intérêt ! Vous trouverez en pièce jointe le guide <strong>"Les 10 erreurs qui font fuir vos clients en ligne"</strong>.
                </p>
                <p style="font-size: 15px; color: #555; line-height: 1.7; margin: 0 0 32px;">
                    Prenez le temps de parcourir chaque point et de vérifier votre site actuel. Si vous identifiez plusieurs de ces erreurs, nous sommes là pour vous aider.
                </p>
                <div style="text-align: center;">
                    <a href="https://entryweb.fr/contact" style="display: inline-block; padding: 14px 32px; background: #0066ff; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">Obtenir mon devis gratuit</a>
                </div>
            </div>
            <div style="padding: 24px 32px; background: #f8f9fa; border-top: 1px solid #eee; text-align: center;">
                <p style="font-size: 12px; color: #aaa; margin: 0;">EntryWeb · entryweb.fr</p>
            </div>
        </div>
        HTML;

        $mailPdf = (new Email())
            ->from($contactEmail)
            ->to($email)
            ->subject('Votre guide gratuit EntryWeb — Les 10 erreurs qui font fuir vos clients')
            ->html($htmlContent)
            ->text('Merci ! Votre guide "Les 10 erreurs qui font fuir vos clients en ligne" est en pièce jointe.')
            ->addPart(new DataPart($pdfContent, 'guide-entryweb-10-erreurs.pdf', 'application/pdf'));

        try {
            $mailer->send($mailPdf);
            $subscriber->setPdfSent(true);
        } catch (TransportExceptionInterface) {
            // PDF send failed but we still save the subscriber
        }

        $welcomeHtml = <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: #0066ff; padding: 32px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">EntryWeb</h1>
                <p style="color: #cce0ff; margin: 8px 0 0; font-size: 14px;">Un mot de nous</p>
            </div>
            <div style="padding: 40px 32px;">
                <h2 style="font-size: 22px; color: #1a1a2e; margin: 0 0 20px; line-height: 1.3;">Bonjour !</h2>
                <p style="font-size: 15px; color: #555; line-height: 1.8; margin: 0 0 16px;">
                    Nous sommes <strong>Florian et Émilien</strong>, co-fondateurs d'EntryWeb. Merci de nous avoir rejoints.
                </p>
                <p style="font-size: 15px; color: #555; line-height: 1.8; margin: 0 0 16px;">
                    On a créé EntryWeb parce qu'on voyait trop d'artisans et d'entrepreneurs avec des sites trop chers, trop complexes, et qui ne leur ressemblent pas. Notre mission : vous donner les mêmes outils que les grandes structures, avec un budget adapté à la réalité d'une petite entreprise.
                </p>
                <p style="font-size: 15px; color: #555; line-height: 1.8; margin: 0 0 32px;">
                    De temps en temps, on partage des conseils concrets sur la visibilité en ligne et nos nouveaux articles. Pas de masse, juste ce qui peut vraiment vous être utile.
                </p>
                <p style="font-size: 14px; color: #888; line-height: 1.6; margin: 0; border-top: 1px solid #eee; padding-top: 24px;">
                    À très vite,<br>
                    <strong style="color: #1a1a2e;">Florian &amp; Émilien</strong><br>
                    Co-fondateurs, EntryWeb · <a href="https://entryweb.fr" style="color: #0066ff; text-decoration: none;">entryweb.fr</a>
                </p>
            </div>
        </div>
        HTML;

        $mailWelcome = (new Email())
            ->from($contactEmail)
            ->to($email)
            ->subject('Un mot de Florian & Émilien — EntryWeb')
            ->html($welcomeHtml)
            ->text("Bonjour,\n\nNous sommes Florian et Émilien, co-fondateurs d'EntryWeb. Merci de nous avoir rejoints.\n\nOn a créé EntryWeb parce qu'on voyait trop d'artisans avec des sites trop chers et trop complexes. Notre mission : vous donner les mêmes outils que les grandes structures, avec un budget adapté.\n\nDe temps en temps, on partage des conseils concrets et nos nouveaux articles. Pas de masse, juste ce qui peut vous être utile.\n\nÀ très vite,\nFlorian & Émilien\nCo-fondateurs, EntryWeb — entryweb.fr");

        try {
            $mailer->send($mailWelcome);
        } catch (TransportExceptionInterface) {
            // welcome send failed silently
        }

        $entityManager->flush();

        if ($isAjax) {
            return new JsonResponse([
                'status' => 'success',
                'title' => 'Inscription confirmée !',
                'message' => 'Votre guide + un mail de bienvenue viennent de vous être envoyés. Vérifiez votre boîte mail (et vos spams si besoin).',
            ]);
        }

        $this->addFlash('newsletter_success', 'pdf_sent');

        return $this->redirectToRoute('app_home', ['_fragment' => 'newsletter']);
    }

    #[Route('/realisations/{id}', name: 'app_realisation_show', requirements: ['id' => '\d+'])]
    public function realisationShow(Realisation $realisation): Response
    {
        if (!$realisation->isPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render('pages/realisation_show.html.twig', [
            'realisation' => $realisation,
        ]);
    }

    #[Route('/faq', name: 'app_faq')]
    public function faq(): Response
    {
        return $this->render('pages/faq.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_legal')]
    public function legal(): Response
    {
        return $this->render('pages/legal.html.twig');
    }

    #[Route('/cgv', name: 'app_cgv')]
    public function cgv(): Response
    {
        return $this->render('pages/cgv.html.twig');
    }

    #[Route('/politique-confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('pages/privacy.html.twig');
    }

    #[Route('/parrainage', name: 'app_parrainage', methods: ['GET', 'POST'])]
    public function parrainage(Request $request, ParrainageRepository $parrainageRepository, EntityManagerInterface $entityManager): Response
    {
        $parrainage = null;

        // Store referral code in session when visiting with ?ref=
        $ref = $request->query->getString('ref');
        if ('' !== $ref) {
            $request->getSession()->set('referral_code', $ref);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parrainage_form', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide, veuillez réessayer.');

                return $this->redirect($this->generateUrl('app_parrainage').'#devenir-parrain');
            }

            $email = trim($request->request->getString('email'));

            if ('' !== $email) {
                $parrainage = $parrainageRepository->findByEmail($email);

                if (null === $parrainage) {
                    do {
                        $code = 'ENT-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    } while (null !== $parrainageRepository->findByReferralCode($code));

                    $parrainage = new Parrainage();
                    $parrainage->setEmail($email);
                    $parrainage->setReferralCode($code);

                    $entityManager->persist($parrainage);
                    $entityManager->flush();
                }

                $request->getSession()->set('parrainage_email', $email);

                return $this->redirect($this->generateUrl('app_parrainage').'#devenir-parrain');
            }
        }

        // Retrieve parrainage from session after redirect
        $parrainageEmail = $request->getSession()->remove('parrainage_email');
        if (null !== $parrainageEmail) {
            $parrainage = $parrainageRepository->findByEmail($parrainageEmail);
        }

        return $this->render('pages/parrainage.html.twig', [
            'parrainage' => $parrainage,
        ]);
    }
}
