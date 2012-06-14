<?php
namespace Alchemy\Kernel\EventListener;

use Alchemy\Component\EventDispatcher\EventSubscriberInterface;
use Alchemy\Kernel\Event\ControllerEvent;
use Alchemy\Net\Http\Response;
use Alchemy\Kernel\KernelEvents;

class ControllerListener implements EventSubscriberInterface
{
    public function __construct()
    {

    }

    public function onControllerHandling(ControllerEvent $event)
    {
        // getting information from ControllerEvent object
        $controller = $event->getController();
        $arguments  = $event->getArguments();

        // call (execute) the controller method
        $response = call_user_func_array($controller, $arguments);

        // check returned value by method
        if (is_array($response)) { // if it returns a array
            // set controller view data object
            foreach ($response as $key => $value) {
                $controller[0]->view->$key = $value;
            }
        } elseif ($response instanceof Response) {
            $controller[0]->setResponse($response);
        }
    }

    static public function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array('onControllerHandling'),
        );
    }
}