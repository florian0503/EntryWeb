<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    private const ARTICLES_PER_PAGE = 6;

    #[Route('/blog', name: 'app_blog')]
    #[Route('/blog/page/{page}', name: 'app_blog_page', requirements: ['page' => '\d+'])]
    public function index(Request $request, ArticleRepository $articleRepository, int $page = 1): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $search = '' !== $query ? $query : null;

        if (1 === $page && 'app_blog_page' === $request->attributes->get('_route')) {
            return $this->redirectToRoute('app_blog', $search ? ['q' => $search] : [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $total = $articleRepository->countPublished($search);
        $totalPages = max(1, (int) ceil($total / self::ARTICLES_PER_PAGE));

        if ($page > $totalPages) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        $articles = $articleRepository->findPublishedPaginated(
            ($page - 1) * self::ARTICLES_PER_PAGE,
            self::ARTICLES_PER_PAGE,
            $search
        );

        $showFeatured = 1 === $page && null === $search;

        return $this->render('pages/blog/index.html.twig', [
            'articles' => $articles,
            'featured' => $showFeatured ? ($articles[0] ?? null) : null,
            'rest' => $showFeatured ? array_slice($articles, 1) : $articles,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'q' => $query,
        ]);
    }

    #[Route('/blog/{slug}', name: 'app_blog_show')]
    public function show(string $slug, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->findOnePublishedBySlug($slug);

        if (!$article) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        $related = array_filter(
            $articleRepository->findPublished(),
            fn ($a) => $a->getId() !== $article->getId() && $a->getCategory() === $article->getCategory()
        );

        return $this->render('pages/blog/show.html.twig', [
            'article' => $article,
            'related' => array_slice(array_values($related), 0, 3),
        ]);
    }
}
