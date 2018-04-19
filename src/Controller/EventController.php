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
use App\Entity\Event\Message;
use App\Entity\Event\Publisher;

class EventController extends Controller
{
    /**
     * @Route("/event/{eventID}", name="event")
     */
    public function index($eventID, EventStats $stats)
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
        
        $messages = $em->getRepository('\App\Entity\Event\Message')->findLatestEventMessageByBroadcastDate($event, new \DateTime('- 6 hours'));
        $stats = $stats->getStatsCards($event);
        
        return $this->render('event/index.html.twig', [
            'event' => $event,
            'stats' => $stats,
            'messages' => $messages
        ]);
    }
    
    /**
     * @Route("/event/{eventID}/post", name="post_message")
     */
    public function post_event_message($eventID, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->find($eventID);
        
        $success = false;
        
        if (is_null($event)) {
            $event = $em->getRepository('\App\Entity\Event\Event')->findBySlug($eventID)[0];
            if (is_null($event)) {
                throw $this->createNotFoundException('Unknown event');
            }
        }
        
        if ($event->getStartDate() > new \DateTime('now') || $event->getEndDate() < new \DateTime('now')) {
            return $this->render('event/auth.html.twig', array(
                'form' => null,
                'event' => $event,
                'error' => false,
                'closed' => true
            ));
        }
        
        $session = $request->getSession();
        
        if (!$session->get('post_access_key/'.$eventID)) {
            $error = false;
            $form = $this->createFormBuilder()
            ->add('access_key', TextType::class, array('required' => true, 'label' => 'event.form.access_key'))
            ->add('send', SubmitType::class, array('label' => 'event.form.send'))
            ->getForm();
            
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $publisher = $em->getRepository('\App\Entity\Event\Publisher')->findOneBy(array('access_key' => $data['access_key']));
                if ($publisher && $publisher->getEvent() === $event) {
                    $session->set('post_access_key/'.$eventID, $data['access_key']);
                    return $this->redirectToRoute('post_message', array('eventID' => $eventID));
                } else {
                    $error = true;
                }
            }
            
            return $this->render('event/auth.html.twig', array(
                'form' => $form->createView(),
                'event' => $event,
                'error' => $error,
                'closed' => false
            ));
        } else {
            $authKey = $session->get('post_access_key/'.$eventID);
            $publisher = $em->getRepository('\App\Entity\Event\Publisher')->findOneBy(array('access_key' => $authKey));
            if (!$publisher) {
                return $this->redirectToRoute('post_logout', array('eventID' => $eventID));
            }
            
            $message = new Message();
            $message->setEvent($event);
            
            $form = $this->createFormBuilder($message)
            ->add('message_line_1', TextType::class, array('attr' => array('maxlength ' => '65'), 'label' => 'event.form.message_line_1'))
            ->add('message_line_1_important', CheckboxType::class,  array('required' => false, 'mapped' => false, 'label' => 'event.form.highlight'))
            ->add('message_line_2', TextType::class, array('attr' => array('maxlength ' => '65', 'autocapitalize' => 'off'), 'label' => 'event.form.message_line_2'))
            ->add('message_line_2_important', CheckboxType::class,  array('required' => false, 'mapped' => false, 'label' => 'event.form.highlight'))
            ->add('message_line_3', TextType::class, array('attr' => array('maxlength ' => '65', 'autocapitalize' => 'off'), 'label' => 'event.form.message_line_3'))
            ->add('message_line_3_important', CheckboxType::class,  array('required' => false, 'mapped' => false, 'label' => 'event.form.highlight'))
            ->add('startTime', TimeType::class,  array('mapped' => false, 'widget' => 'single_text', 'required' => true, 'label' => 'event.form.start_time'))
            ->add('save', SubmitType::class, array('label' => 'event.form.submit'))
            ->getForm();
            
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid() && $publisher->getRemainingMessages() > 0 && (is_null($publisher->getLastPublicationDate()) || $publisher->getMinutesSinceLastPublication() >= 120)) {
                $message = $form->getData();
                
                // To complicated for no good reason
                $minutesToAdd = ($form->get('startTime')->getData()->format('H') * 60) + $form->get('startTime')->getData()->format('i');
                $startDate = new \DateTime('today midnight');
                $startDate->modify('+'.$minutesToAdd.' minutes');
                $endDate = clone $startDate;
                $endDate = $endDate->modify('+ 10 minutes');
                
                if ($form->get('message_line_1_important')->getData()) {
                    $message->setMessageLine1('<span class="info-major">' . $message->getMessageLine1() . '</span>');
                }
                if ($form->get('message_line_2_important')->getData()) {
                    $message->setMessageLine2('<span class="info-major">' . $message->getMessageLine2() . '</span>');
                }
                if ($form->get('message_line_3_important')->getData()) {
                    $message->setMessageLine3('<span class="info-major">' . $message->getMessageLine3() . '</span>');
                }
                
                $message->setStartDate($startDate);
                $message->setEndDate($endDate);
                $message->setPublisher($publisher);
                $em->persist($message);
                
                $publisher->setLastPublicationDate(new \DateTime('now'));
                $publisher->setRemainingMessages($publisher->getRemainingMessages() - 1);
                $em->persist($publisher);
                
                $em->flush();
                $success = true;
            }
            
            return $this->render('event/post.html.twig', array(
                'form' => $form->createView(),
                'event' => $event,
                'publisher' => $publisher,
                'success' => $success
            ));
        }

    }
    
    /**
     * @Route("/event/{eventID}/logout", name="post_logout")
     */
    public function post_logout($eventID, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->find($eventID);
        
        if (is_null($event)) {
            $event = $em->getRepository('\App\Entity\Event\Event')->findBySlug($eventID)[0];
            if (is_null($event)) {
                throw $this->createNotFoundException('Unknown event');
            }
        }
        
        $session = $request->getSession();
        $session->remove('post_access_key/'.$eventID);
        return $this->redirectToRoute('post_message', array('eventID' => $eventID));
    }
    
    /**
     * @Route("/event/{eventID}/publishers", name="post_mass_create_publishers")
     */
    public function post_mass_create_publishers($eventID, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $success = false;
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->find($eventID);
        
        if (is_null($event)) {
            $event = $em->getRepository('\App\Entity\Event\Event')->findBySlug($eventID)[0];
            if (is_null($event)) {
                throw $this->createNotFoundException('Unknown event');
            }
        }
        
        $form = $this->createFormBuilder()
        ->add('publishers_list', TextareaType::class, array('required' => true, 'label' => 'event.form.publishers'))
        ->add('send', SubmitType::class, array('label' => 'event.form.register'))
        ->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            foreach (explode("\r\n", $data['publishers_list']) as $publisherName) {
                $publisher = new Publisher();
                $publisher->setName(trim($publisherName));
                $publisher->setEvent($event);
                $em->persist($publisher);
            }
            $em->flush();
            $success = true;
        }
        
        return $this->render('event/create_publishers.html.twig', array(
            'form' => $form->createView(),
            'event' => $event,
            'success' => $success
        ));
    }
}