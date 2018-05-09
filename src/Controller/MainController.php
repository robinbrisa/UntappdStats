<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Service\EventStats;
use App\Service\Tools;
use App\Entity\Event\TapListQueue;
use App\Entity\Event\Session;
use Unirest;

class MainController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function index()
    {
        $em = $this->getDoctrine()->getManager();
        $events = $em->getRepository('\App\Entity\Event\Event')->findCurrentEvents();
        $upcomingEvents = $em->getRepository('\App\Entity\Event\Event')->findUpcomingEvents();
        $previousEvents = $em->getRepository('\App\Entity\Event\Event')->findPreviousEvents();
        return $this->render('main/index.html.twig', [
            'currentEvents' => $events,
            'upcomingEvents' => $upcomingEvents,
            'previousEvents' => $previousEvents,
        ]);
    }
    
    /**
     * @Route("/loadmbcc/", name="load_mbcc")
     */
    public function load_mbcc()
    {
        // date example : 2018-05-03T12:13:24Z
        $date = $_GET['date'];
        $diff = $_GET['diff'];
        $beerIDs = array();
        
        $headers = array('Accept' => 'application/json');
        $query = array(
            '$lastLocallyModified' => '"'.$date.'"',
            'query' => '%7B%22updated%22%3A%20*%5B_updatedAt%20%3E%20%24lastLocallyModified%5D%5B0...5000%5D,%22ids%22%3A%20*%5B0...5000%5D._id%7D'
        );
        $response = Unirest\Request::get('https://d76bptot.api.sanity.io/v1/data/query/2018', $headers, $query);
                
        $beers = array();
        $sessions = array();
        $breweries = array();
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->findOneBy(array('name' => "Mikkeller Beer Celebration Copenhagen 2018"));
        foreach($response->body->result->updated as $element) {
            if ($element->_type == "session") {
                if(!array_key_exists($element->name, $sessions)) {
                    $sessions[$element->name] = array();
                }
                foreach ($element->beers as $beer) {
                    $sessions[$element->name][] = $beer->_key;
                }
            }
            if ($element->_type == "beer") {
                $beers[$element->_id] = $element;
                if (isset($element->untappdId)) {
                    $beerIDs[$element->untappdId] = $element->name;
                }
            }
            if ($element->_type == "brewery") {
                $breweries[$element->_id] = $element;
            }
        }
        if (!$diff) {
            foreach ($sessions as $name => $session) {
                echo '<b>'.$name.'</b><br>';
                $sessionEntity = $em->getRepository('\App\Entity\Event\Session')->findOneBy(array('name' => $name, 'event' => $event));
                foreach ($session as $beer) {
                    if (isset($beers[$beer])) {
                        if (isset($beers[$beer]->untappdId)) {
                            echo $beers[$beer]->name.'<br>';
                            $queueElement = new TapListQueue();
                            $queueElement->setSession($sessionEntity);
                            $queueElement->setUntappdID($beers[$beer]->untappdId);
                            $em->merge($queueElement);
                        } else {
                            //echo $beers[$beer]->name.' ('.$breweries[$beers[$beer]->brewery->_ref]->name.')<br>';
                            echo "NO UNTAPPD ID: ".$beers[$beer]->name.'<br>';
                        }
                    }
                }
            }
            $em->flush();
        } else {
            $currentBeers = array();
            foreach ($event->getSessions() as $eventSession) {
                foreach ($eventSession->getBeers() as $sessionBeer) {
                    $currentBeers[$sessionBeer->getId()] = $sessionBeer->getName();
                }
            }
            
            echo '<h3><strong>Currently missing</strong></h3>';
            foreach (array_diff_key($beerIDs, $currentBeers) as $diffKey => $diffValue) {
                echo $diffKey . " (" . $diffValue . ")<br>";
            }
            echo '<h3><strong>To be deleted</strong></h3>';
            foreach (array_diff_key($currentBeers, $beerIDs) as $diffKey => $diffValue) {
                echo $diffKey . " (" . $diffValue . ")<br>";
            }
        }
        return $this->render('main/debug.html.twig', []);
    }
    
    /**
     * @Route("/debug/{id}", name="debug")
     */
    public function debug($id, EventStats $stats, Tools $tools)
    {
        $em = $this->getDoctrine()->getManager();
        $event = $em->getRepository('\App\Entity\Event\Event')->find($id);
        $venues = $event->getVenues();
        $me = $em->getRepository('\App\Entity\User\User')->find(2278575);
        $attending = $em->getRepository('\App\Entity\Event\Event')->getFutureOrCurrentEventsUserIsAttending($me);
        foreach ($attending as $anEvent) {
            dump($tools->getEventBeersUserHasCheckedIn($me, $anEvent));
        }
        //dump($apiKeys);
        
        $messages = $em->getRepository('\App\Entity\Event\Message')->findBy(array('event' => $event), array('start_date' => 'ASC'));
        echo '<div class="container">';
        
        echo '<h2>Debug dump for ' . $event->getName() . '</h2>';
        
        echo '<h3>Programmed messages / notifications</h3>';
        
        foreach ($messages as $message) {
            $dateRange = null;
            if ($message->getStartDate()) {
                $dateRange = '<b>Starting:</b> ' . $message->getStartDate()->format('d/m/Y H:i:s');
            }
            if ($message->getEndDate()) {
                if ($dateRange) {
                    $dateRange .= "<br />";
                }
                $dateRange .= '<b>Ending:</b> ' . $message->getEndDate()->format('d/m/Y H:i:s');
            }
            if (!$dateRange) {
                $dateRange = "Permanent";
            }
            echo '<div>' . $dateRange . '</div>';
            echo '<div id="info-content">';
            echo '<div class="line"><span class="info-line-text">' . $message->getMessageLine1() . '</span></div>';
            echo '<div class="line"><span class="info-line-text">' . $message->getMessageLine2() . '</span></div>';
            echo '<div class="line"><span class="info-line-text">' . $message->getMessageLine3() . '</span></div>';
            echo '</div>';
        }
        
        $statsDebug = $stats->debugStatistics($event);
        
        echo '<h3>Displayed stats</h3>';
        
        echo '<div style="width: 500px; margin-left: 30px;">';
        foreach ($statsDebug as $function => $value) {
            echo '<div>'.$function.'</div>';
            if ($value) {
                echo '<div id="info-content">';
                echo '<div class="line"><span class="info-line-text">' . $value['line1'] . '</span></div>';
                echo '<div class="line"><span class="info-line-text">' . $value['line2'] . '</span></div>';
                echo '<div class="line"><span class="info-line-text">' . $value['line3'] . '</span></div>';
                echo '</div>';
            } else {
                echo '<div>Returned false</div>';
            }
        }
        echo '</div>';
        
        echo '<h3>Style colors</h3>';
        
        $styles = $em->getRepository('\App\Entity\Beer\Style')->findAll();
        
        foreach ($styles as $style) {
            echo '<div id="info-content" style="width:500px;">';
            echo '<div class="line">';
            echo '<div class="color-wrapper live-style-color-container"><div class="ranking-style-color" style="float:left; background-color: ' . $style->getColor() . '; width:32px;"></div></div>';
            echo '<span class="name">' . $style->getName() . '</span>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        return $this->render('main/debug.html.twig', [
        ]);
    }
    
    
    
    /**
     * @Route("/global", name="global_stats")
     */
    public function globalStats()
    {
        $em = $this->getDoctrine()->getManager();
        $stats = array();
        
        $stats['total_checkins'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getTotalCheckinsCount();
        $stats['most_toasts'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getCheckinWithMostToasts();
        $stats['most_comments'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getCheckinWithMostComments();
        $stats['most_badges'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getCheckinWithMostBadges();
        $stats['rating_avg'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getAverageRatingByCheckin();
        $stats['ratings_by_score'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getRatingsCountByScore();
        $stats['most_checked_in_beer'] = $em->getRepository('\App\Entity\Beer\Beer')->getMostCheckedInBeer();
        $stats['most_checked_in_brewery'] = $em->getRepository('\App\Entity\Brewery\Brewery')->getMostCheckedInBrewery();
        $stats['most_checked_in_brewery_unique'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getMostCheckedInUniqueBrewery();
        $stats['best_rated_brewery'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getBestRatedBrewery();
        $stats['checkin_history_per_day'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getCheckinHistoryPerDay();
        $stats['day_with_most_checkins'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getDayWithMostCheckins();
        $stats['month_with_most_checkins'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getMonthWithMostCheckins();
        $stats['year_with_most_checkins'] = $em->getRepository('\App\Entity\Checkin\Checkin')->getYearWithMostCheckins();
        
        $ratings = array('0.25', '0.50', '0.75', '1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '3.25', '3.50', '3.75', '4.00', '4.25', '4.50', '4.75', '5.00');
        
        return $this->render('main/global.html.twig', [
            'stats' => $stats,
            'ratings' => $ratings
        ]);
    }
}
