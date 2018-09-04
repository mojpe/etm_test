<?php

namespace AppBundle\Controller\Api;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Entity\Offer;
use Unirest;

class OfferController extends Controller
{

  /**
  * @Route("/api/offers")
  * @Method("GET")
  */
  public function getAction(Request $request)
  {
    // look for *all* Offers objects
    $repository = $this->getDoctrine()->getRepository(Offer::class);
    // find *all* products
    $offers = $repository->findAll();

    foreach($offers as $offer)
    {
      $data[] = [
        'application_id' => $offer->getApplicationId(),
        'country'        => $offer->getCountry(),
        'payout'         => $offer->getPayout(),
        'name'           => $offer->getName(),
        'platform'       => $offer->getPlatform()
      ];
    }

    // Display the result
    return new JsonResponse($data);

  }

  /**
  * @Route("/api/offer/")
  * @Method("POST")
  */
  public function postAction(Request $request)
  {
    // get advertiser id from url
    $advertiser_id = $request->get('advertiser_id');

    // Search Advertiser
    $headers = array('Accept' => 'application/json');
    $url = 'http://process.xflirt.com/advertiser/'.$advertiser_id.'/offers';
    $response = Unirest\Request::get($url,$headers);
    $body = $response->body;

    foreach($body as $value)
    {
      // Generating unique ID
      $application_id = uniqid();

      if($advertiser_id == 2)
      {
          $payout = $this->pointCalculator($value->campaigns->points);
          $alpha3 = $value->campaigns->countries[0];
          $country = $this->convertCountryCode($alpha3);

          // Create a new empty object
          $offer = new Offer();
          // Use methods from the Quote entity to set the values
          $offer->setApplicationId($application_id);
          $offer->setCountry($country);
          $offer->setPayout($payout);
          $offer->setName($value->app_details->category);
          $offer->setPlatform($value->app_details->platform);
          // Get the Doctrine service and manager
          $em = $this->getDoctrine()->getManager();
          // Add our quote to Doctrine so that it can be saved
          $em->persist($offer);

      } else {
        // Create a new empty object
        $offer = new Offer();
        // Use methods from the Quote entity to set the values
        $offer->setApplicationId($application_id);
        $offer->setCountry($value->countries[0]);
        $offer->setPayout($value->payout_amount);
        $offer->setName($value->name);
        $offer->setPlatform($value->mobile_platform);
        // Get the Doctrine service and manager
        $em = $this->getDoctrine()->getManager();
        // Add our quote to Doctrine so that it can be saved
        $em->persist($offer);
      }

    } // end of foreach

    // Save our quote
    $em->flush();

    return new Response('Offer for Advertiser has been saved', 200);
  }

  /* Function for calculatinng points in to USD currency */
  public function pointCalculator($points)
  {
    //10 points = $0.01
    $point_value = 0.01;
    $payment = ($points/10) * $point_value;

    return $payment;
  }

  /* Function for converting Country ISO from alpha3 to alpha2 */
  public function convertCountryCode($alpha3)
  {
	  // Search Advertiser
    $headers = array('Accept' => 'application/json');
    $url = 'http://country.io/iso3.json';
    $response = Unirest\Request::get($url,$headers);

    $country_iso = (array)$response->body;
    $alpha2 = array_search($alpha3, $country_iso);

	  return $alpha2;
  }


}
