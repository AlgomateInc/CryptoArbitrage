<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 3/18/2015
 * Time: 10:47 PM
 */

require_once(__DIR__ . '/../BaseStrategy.php');
require_once('SimpleOrderInstructions.php');

/**
 * Class MakerEstablishPositionInstructions
 *
 * This class establishes positions by entering limit orders close to the inside of the bid/ask.
 * The objective is to enter a position without incurring fees and moving the market
 */
class MakerEstablishPositionStrategy extends BaseStrategy {

    public function run($instructions, $markets, $balances)
    {
        $soi = new SimpleOrderInstructions();
        $soi->load($instructions);

        $market = $this->findMarket($markets, $soi->exchange, $soi->currencyPair);
        if (!($market instanceof IExchange))
            return null;

        $depth = $market->depth($soi->currencyPair);
        if (!($depth instanceof OrderBook))
            return null;

        if (count($depth->bids) > 0 && count($depth->asks) > 0) {
            $insideBid = $depth->bids[0];
            $insideAsk = $depth->asks[0];

            if ($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem) {
                $soi->price = Currency::FloorValue(($insideAsk->price + $insideBid->price) / 2.0,
                    CurrencyPair::Quote($soi->currencyPair));

                if($soi->price > $insideBid->price && $soi->price < $insideAsk->price)
                    return $soi;
            }
        }

        return null;
    }
}