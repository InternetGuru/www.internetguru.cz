<?php
$I = new AcceptanceTester($scenario);
$I->wantTo('get details about Patriot');
$I->amOnPage('');
#$I->amOnPage('hotel_patriot');
$I->click(['link' => 'Hotel Patriot']);
$I->see('Hotel Patriot','//body/div[@id="content"]/div[1]/div/h1');