<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Service\EventStats;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Service\Tools;
use Doctrine\Common\Collections\Criteria;

class TaplistController extends Controller
{
    /**
     * @Route("/taplist/{eventID}/", name="taplist")
     */
    public function index($eventID, Request $request, Tools $tools)
    {
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->find($eventID);
        
        if (is_null($event)) {
            $event = $em->getRepository('\App\Entity\Event\Event')->findBySlug($eventID);
            if (!$event) {
                throw $this->createNotFoundException('Unknown event');
            }
            $event = $event[0];
        }
        
        $session = $request->getSession();
        $session->set('tlid', $eventID);
        $styleCategories = $em->getRepository('\App\Entity\Beer\Category')->findAll();
        
        $user = null;
        $userData = null;
        $checkedInBeers = array();
        
        if ($userUntappdID = $session->get('userUntappdID')) {
            $user = $em->getRepository('\App\Entity\User\User')->find($userUntappdID);
            $event = $em->getRepository('\App\Entity\Event\Event')->find($event);
            $userData = $em->getRepository('\App\Entity\User\SavedData')->findOneBy(array('user' => $user, 'event' => $event));
            $checkedInBeers = $tools->getEventBeersUserHasCheckedIn($user, $event);
            $criteria = Criteria::create()->where(Criteria::expr()->eq("id", $event->getId()));
            if (count($user->getAttending()->matching($criteria)) == 0) {
                $user->addAttending($event);
                $em->persist($user);
                $em->flush();
            }
        }
        
        $outOfStockTapListItems = $em->getRepository('\App\Entity\Event\TapListItem')->getOutOfStockBeers($event);
        
        $outOfStock = array();
        foreach ($outOfStockTapListItems as $outOfStockTapListItem) {
            if (!array_key_exists($outOfStockTapListItem->getSession()->getId(), $outOfStock)) {
                $outOfStock[$outOfStockTapListItem->getSession()->getId()] = array();
            }
            $outOfStock[$outOfStockTapListItem->getSession()->getId()][] = $outOfStockTapListItem->getBeer()->getId();
        }
                
        return $this->render('taplist/index.html.twig', [
            'event' => $event,
            'styleCategories' => $styleCategories,
            'user' => $user,
            'userData' => $userData,
            'checkedInBeers' => json_encode($checkedInBeers),
            'outOfStock' => json_encode($outOfStock)
        ]);
    }
}
