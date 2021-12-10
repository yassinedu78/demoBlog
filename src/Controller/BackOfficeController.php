<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class BackOfficeController extends AbstractController
{
    # Méthode qui affiche la page Home du backoffice
    #[Route('/admin', name: 'app_admin')]
    public function adminHome(): Response
    {
        return $this->render('back_office/index.html.twig');
    }

     # Méthode qui affiche la page Home du backoffice
     #[Route('/admin/articles', name: 'app_admin_articles')]
     #[Route('/admin/articles/{id}/remove', name: 'app_admin_articles_remove')]
     public function adminArticles(EntityManagerInterface $manager, ArticleRepository $repoArticle, Article $artRemove = null): Response
     {

        // dd($artRemove);
        //
        $colonnes = $manager->getClassMetadata(Article::class)->getFieldNames();
        
        // SELECT * FROM article + FETCHALL
        $tableau = $repoArticle->findAll();
        // dd($tableau);

        //Traitement suppression article en BDD
        if($artRemove)
        {
            // Avant de supprimer l'article dans la bdd, on stock son ID afin de l'intégrer dans le message de validation de suppression (addFlash)
            $id = $artRemove->getId();

            $manager->remove($artRemove);
            $manager->flush();

            $this->addFlash('success', "l'article a bien été supprimé.");

            return $this->redirectToRoute('app_admin_articles');
        }

        /*
            Exo : Afficher sous forme de tableau HTML l'ensemble des articles stockés en BDD
            1. Selectionner en BDD l'ensemble de la table 'article' et transmettre le résultat à la méthode render()
            2. Dans le template 'admin_article.html.twig', mettre en forme l'affichage des articles dans un tableau HTML
            3. Afficher le nom de la catégorie de chaque article
            4. Afficher le nombre de commentaire de chaque article
            5. Prévoir un lien modification/suppression pour chaque article
        */

        return $this->render('back_office/admin_articles.html.twig', [
            'colonnes' => $colonnes,
            'tableau' => $tableau
        ]);
     }

     /*
        Exo : création d'une nouvelle méthode permettant d'insérer et de modifier 1 article en BDD
        1. Créer une route '/admin/article/add' (name:app_admin_article_add)
        2. Créer la méthode adminArticleForm()
        3. Créer un nouveau template 'admin_article_form.html.twig'
        4. Importer et créer le formulaire au sein de la méthode adminArticleForm() (createForm)
        5. Afficher le formulaire sur le template 'admin_article_form.html.twig'
        6. Gérer l'upload de la photo
        7. Dans la méthode adminArticleForm(), réaliser le traitement permettant d'insérer un nouvel article en BDD (persist() / flush()) 
     */

    #[Route('/admin/article/add', name: 'app_admin_article_add')]
    #[Route('/admin/article/{id}/update', name: 'app_admin_article_update')]
    public function adminArticleForm(Request $request, EntityManagerInterface $manager, Article $article = null): Response
    {
        // Si $article contient un article de la BDD, on stock une variable la photo de l'article afin de la renvoyer en BDD si nous ne modifions pas la photo de l'article 
        if($article)
        {
            $photoActuelle = $article->getPhoto();
        }

        if(!$article)
        {
            $article = new Article;
        }
        
        $formArticle = $this->createForm(ArticleType::class, $article);

        $formArticle->handleRequest($request);

        if($formArticle->isSubmitted() && $formArticle->isValid())
        {
            if($article->getId())
                $txt = 'modifié';
            else
                $txt = 'enregistré';

            $article->setDate(new \DateTime());

            // dd($article);

            // DEBUT TRAITEMENT PHOTO
            $photo = $formArticle->get('photo')->getData();
            // dd($photo);

            if($photo)
            {
                $nomOriginePhoto = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                // dd($nomOriginePhoto);

                $nouveauNomFichier = $nomOriginePhoto . '-' . uniqid() . '-' . $photo->guessExtension();
                // dd($nouveauNomFichier);

                try
                {
                    $photo->move(
                        $this->getParameter('photo_directory'),
                        $nouveauNomFichier
                    );
                }
                catch(FileException $e)
                {

                }

                // on enregistre la photo en BDD
                $article->setPhoto($nouveauNomFichier);
            }
            else
            {
                // Si l'article possède une photo mais qu'on ne souhaite pas la modifiée, alors on entre dans la condition IF et on renvoi la même photo dans la BDD
                if(isset($photoActuelle))
                    $article->setPhoto($photoActuelle);
                else
                    // Sinon on crée un nouvel article mais on ne souhaite uplodée d'image, alors on envoi NULL pour la champ photo dans la BDD
                    $article->setPhoto(null);
            }

            // FIN TRAITEMENT PHOTO

            $manager->persist($article);
            $manager->flush();

            $this->addFlash('success', "L'article a été $txt avec succès.");

            return $this->redirectToRoute('app_admin_articles');
        }

        return $this->render('back_office/admin_article_form.html.twig', [
            'formArticle' => $formArticle->createView(),
            'photoActuelle' => $article->getPhoto()
        ]);
    }

    /*
        Exo: affichage et suppression catégorie
        1. Création d'une nouvelle route '/admin/categories' (name: app_admin_categories)
        2. Création d'une nouvelle méthode adminCategories()
        3. Création d'un nouveau template 'admin_categories.html.twig
        4. Selectionner les noms des champs/colonnes de la table Category, les transmettre au template et les afficher
        5. Selectionner dans le controller l'ensemble de la table 'category' (findAll) et transmettre au template (render) et les aficher sur le template (Twig), afficher également le nombre d'article à chaque catégorie
        6. Prévoir un lien 'modifier' et 'supprimer' pour chaque categorie
        7. Réaliser le traitement permettant de supprimer une catégorie de la BDD 
    */

    #[Route('/admin/categories', name: 'app_admin_categories')]
    public function adminCategories(EntityManagerInterface $manager, CategoryRepository $repoCategory): Response
    {
        $colonnes = $manager->getClassMetadata(Category::class)->getFieldNames();
        // dd($colonnes);

        $allCategory = $repoCategory->findAll();
        // dd($allCategory);

        return $this->render('back_office/admin_categories.html.twig', [
            'colonnes' =>$colonnes,
            'allCategory' => $allCategory
        ]);
    }
}
