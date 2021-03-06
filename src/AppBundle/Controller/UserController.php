<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller {
    /*
	 * displays details about a user and the list of decklists he published
	 */
    public function publicProfileAction($user_id, $user_name, $page, Request $request) {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge($this->container->getParameter('cache_expiration'));

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $user \AppBundle\Entity\User */
        $user = $em->getRepository('AppBundle:User')->find($user_id);
        if (!$user) {
            throw new NotFoundHttpException("No such user.");
        }

        return $this->render('AppBundle:User:profile_public.html.twig', [
            'user' => $user
        ]);
    }

    public function editProfileAction() {
        $user = $this->getUser();

        $spheres = $this->getDoctrine()->getRepository('AppBundle:Sphere')->findAll();

        return $this->render('AppBundle:User:profile_edit.html.twig', [
            'user' => $user,
            'spheres' => $spheres
        ]);
    }

    public function saveProfileAction(Request $request) {
        /* @var $user \AppBundle\Entity\User */
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        $username = filter_var($request->get('username'), FILTER_SANITIZE_STRING);
        if ($username !== $user->getUsername()) {
            $user_existing = $em->getRepository('AppBundle:User')->findOneBy(['username' => $username]);

            if ($user_existing) {
                $this->get('session')->getFlashBag()->set('error', "Username $username is already taken.");

                return $this->redirect($this->generateUrl('user_profile_edit'));
            }

            $user->setUsername($username);
        }

        $email = filter_var($request->get('email'), FILTER_SANITIZE_STRING);
        if ($email !== $user->getEmail()) {
            $user->setEmail($email);
        }

        $resume = filter_var($request->get('resume'), FILTER_SANITIZE_STRING);
        $sphere_code = filter_var($request->get('user_sphere_code'), FILTER_SANITIZE_STRING);
        $notifAuthor = $request->get('notif_author') ? true : false;
        $notifCommenter = $request->get('notif_commenter') ? true : false;
        $notifMention = $request->get('notif_mention') ? true : false;
        $shareDecks = $request->get('share_decks') ? true : false;

        $user->setColor($sphere_code);
        $user->setResume($resume);
        $user->setIsNotifAuthor($notifAuthor);
        $user->setIsNotifCommenter($notifCommenter);
        $user->setIsNotifMention($notifMention);
        $user->setIsShareDecks($shareDecks);

        $this->getDoctrine()->getManager()->flush();

        $this->get('session')->getFlashBag()->set('notice', 'Successfully saved your profile.');

        return $this->redirect($this->generateUrl('user_profile_edit'));
    }

    public function infoAction(Request $request) {
        $jsonp = $request->query->get('jsonp');

        $decklist_id = $request->query->get('decklist_id');
        $fellowship_id = $request->query->get('fellowship_id');
        $questlog_id = $request->query->get('questlog_id');
        $card_id = $request->query->get('card_id');

        $content = null;

        $securityContext = $this->container->get('security.context');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $user = $this->getUser();
            $user_id = $user->getId();

            $public_profile_url = $this->get('router')->generate('user_profile_public', [
                'user_id' => $user_id,
                'user_name' => urlencode($user->getUsername())
            ]);

            $content = [
                'public_profile_url' => $public_profile_url,
                'id' => $user_id,
                'name' => $user->getUsername(),
                'sphere' => $user->getColor(),
                'donation' => $user->getDonation(),
                'owned_packs' => $user->getOwnedPacks()
            ];

            if (isset($decklist_id)) {
                /* @var $em \Doctrine\ORM\EntityManager */
                $em = $this->getDoctrine()->getManager();
                /* @var $decklist \AppBundle\Entity\Decklist */
                $decklist = $em->getRepository('AppBundle:Decklist')->find($decklist_id);

                if ($decklist) {
                    $decklist_id = $decklist->getId();

                    $dbh = $this->getDoctrine()->getConnection();

                    $content['is_liked'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM decklist d
        				JOIN vote v ON v.decklist_id = d.id
        				WHERE v.user_id = ?
        				AND d.id = ?", [$user_id, $decklist_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_favorite'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM decklist d
        				JOIN favorite f ON f.decklist_id = d.id
        				WHERE f.user_id = ?
        				AND d.id = ?", [$user_id, $decklist_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_author'] = ($user_id == $decklist->getUser()->getId());

                    $content['can_delete'] = ($decklist->getNbcomments() == 0) && ($decklist->getNbfavorites() == 0) && ($decklist->getNbVotes() == 0);
                }
            }

            if (isset($fellowship_id)) {
                /* @var $em \Doctrine\ORM\EntityManager */
                $em = $this->getDoctrine()->getManager();

                /* @var $fellowship \AppBundle\Entity\Fellowship */
                $fellowship = $em->getRepository('AppBundle:Fellowship')->find($fellowship_id);

                if ($fellowship) {
                    $fellowship_id = $fellowship->getId();

                    $dbh = $this->getDoctrine()->getConnection();

                    $content['is_liked'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM fellowship d
        				JOIN fellowship_vote v ON v.fellowship_id = d.id
        				WHERE v.user_id = ?
        				AND d.id = ?", [$user_id, $fellowship_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_favorite'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM fellowship d
        				JOIN fellowship_favorite f ON f.fellowship_id = d.id
        				WHERE f.user_id = ?
        				AND d.id = ?", [$user_id, $fellowship_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_author'] = ($user_id == $fellowship->getUser()->getId());

                    $content['can_delete'] = ($fellowship->getNbcomments() == 0) && ($fellowship->getNbfavorites() == 0) && ($fellowship->getNbVotes() == 0);
                }
            }

            if (isset($questlog_id)) {
                /* @var $em \Doctrine\ORM\EntityManager */
                $em = $this->getDoctrine()->getManager();

                /* @var $questlog \AppBundle\Entity\Questlog */
                $questlog = $em->getRepository('AppBundle:Questlog')->find($questlog_id);

                if ($questlog) {
                    $questlog_id = $questlog->getId();

                    $dbh = $this->getDoctrine()->getConnection();

                    $content['is_liked'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM questlog d
        				JOIN questlog_vote v ON v.questlog_id = d.id
        				WHERE v.user_id = ?
        				AND d.id = ?", [$user_id, $questlog_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_favorite'] = (boolean)$dbh->executeQuery("SELECT
        				count(*)
        				FROM questlog d
        				JOIN questlog_favorite f ON f.questlog_id = d.id
        				WHERE f.user_id = ?
        				AND d.id = ?", [$user_id, $questlog_id])->fetch(\PDO::FETCH_NUM)[0];

                    $content['is_author'] = ($user_id == $questlog->getUser()->getId());

                    $content['can_delete'] = ($questlog->getNbcomments() == 0) && ($questlog->getNbfavorites() == 0) && ($questlog->getNbVotes() == 0);
                }
            }

            if (isset($card_id)) {
                /* @var $em \Doctrine\ORM\EntityManager */
                $em = $this->getDoctrine()->getManager();

                /* @var $card \AppBundle\Entity\Card */
                $card = $em->getRepository('AppBundle:Card')->find($card_id);

                if ($card) {
                    $reviews = $card->getReviews();
                    /* @var $review \AppBundle\Entity\Review */
                    foreach ($reviews as $review) {
                        if ($review->getUser()->getId() === $user->getId()) {
                            $content['review_id'] = $review->getId();
                            $content['review_text'] = $review->getTextMd();
                        }
                    }
                }
            }
        }
        $content = json_encode($content);

        $response = new Response();
        $response->setPrivate();

        if (isset($jsonp)) {
            $content = "$jsonp($content)";
            $response->headers->set('Content-Type', 'application/javascript');
        } else {
            $response->headers->set('Content-Type', 'application/json');
        }
        $response->setContent($content);

        return $response;
    }

    public function remindAction($username) {
        $user = $this->get('fos_user.user_manager')->findUserByUsername($username);
        if (!$user) {
            throw new NotFoundHttpException("Cannot find user from username [$username]");
        }
        if (!$user->getConfirmationToken()) {
            return $this->render('AppBundle:User:remind-no-token.html.twig');
        }

        $this->get('fos_user.mailer')->sendConfirmationEmailMessage($user);

        $this->get('session')->set('fos_user_send_confirmation_email/email', $user->getEmail());

        $url = $this->get('router')->generate('fos_user_registration_check_email');

        return $this->redirect($url);
    }
}
