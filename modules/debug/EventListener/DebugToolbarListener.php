<?php
// florin, 3/19/13, 4:29 PM

namespace GlCMS\Module\Debug\EventListener;

use GlCMS\Event\DBEvents;
use GlCMS\Event\QueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;


class DebugToolbarListener implements EventSubscriberInterface
{
    const DISABLED = 0;
    const ENABLED = 1;

    protected $mode;
    protected $context;

    private $queries;

    public function __construct(\ContextInstance $context, $mode = self::ENABLED)
    {
        $this->mode = (integer) $mode;
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
            DBEvents::QUERY_ENDED => array('onQueryEnd', 34)
        );
    }

    public function onQueryEnd(QueryEvent $event)
    {
        $this->queries[] = $event;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // do not capture redirects or modify XML HTTP Requests
        if ($request->isXmlHttpRequest()) {
            return;
        }

        //TODO treat redirects?

        if (self::DISABLED === $this->mode
            //|| !$response->headers->has('X-Debug-Token')
            || $response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
        ) {
            return;
        }

        $this->injectToolbar($response);
    }

    public function isEnabled()
    {
        return self::DISABLED !== $this->mode;
    }

    /**
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response A Response instance
     */
    protected function injectToolbar(Response $response)
    {
        if (function_exists('mb_stripos')) {
            $posrFunction   = 'mb_strripos';
            $substrFunction = 'mb_substr';
        } else {
            $posrFunction   = 'strripos';
            $substrFunction = 'substr';
        }
        $content = $response->getContent();
        $pos = $posrFunction($content, '</body>');
        if (false !== $pos) {
            $this->context->set('queries', $this->queries);
            $templateHandler = \TemplateHandler::getInstance();
            $toolbar = $templateHandler->compile('./modules/debug/tpl', 'toolbar');
            $content = $substrFunction($content, 0, $pos).$toolbar.$substrFunction($content, $pos);

            $response->setContent($content);
        }
    }

}