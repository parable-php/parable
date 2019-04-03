<?php declare(strict_types=1);

namespace Parable\Framework\Http;

use Parable\Di\Container;
use Parable\Event\EventManager;
use Parable\Framework\EventTriggers;
use Parable\Framework\Exception;
use Parable\Framework\Path;
use Parable\Http\Response;
use Parable\Http\Traits\SupportsOutputBuffers;
use Parable\Routing\Route;

class RouteDispatcher
{
    use SupportsOutputBuffers;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var Path
     */
    protected $path;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Template
     */
    protected $template;

    /**
     * @var Route|null
     */
    protected $dispatchedRoute;

    public function __construct(
        Container $container,
        EventManager $eventManager,
        Path $path,
        Response $response,
        Template $template
    ) {
        $this->container = $container;
        $this->eventManager = $eventManager;
        $this->path = $path;
        $this->response = $response;
        $this->template = $template;
    }

    public function dispatch(Route $route): void
    {
        $this->dispatchedRoute = $route;

        $this->eventManager->trigger(EventTriggers::ROUTE_DISPATCHER_DISPATCH_BEFORE, $route);

        $this->startOutputBuffer();

        $parameters = [];
        foreach ($route->getParameterValues()->getAll() as $value) {
            $parameters[] = $value;
        }

        if ($route->getController() !== null && $route->getAction() !== null) {
            $controller = $this->container->get($route->getController());
            $controller->{$route->getAction()}(...$parameters);
        } elseif ($route->getCallable() !== null) {
            $callable = $route->getCallable();
            $callable(...$parameters);
        }

        $template = $route->getMetadataValue('template');

        if ($template !== null) {
            $this->eventManager->trigger(EventTriggers::ROUTE_DISPATCHER_DISPATCH_TEMPLATE_BEFORE, $template);

            $template = $this->path->getPath((string)$template);

            if (!file_exists($template)) {
                throw new Exception(sprintf(
                    "Template path '%s' does not exist.",
                    $template
                ));
            }

            $this->template->setTemplatePath($template);
            $this->template->render();

            $this->eventManager->trigger(EventTriggers::ROUTE_DISPATCHER_DISPATCH_TEMPLATE_AFTER, $template);
        }

        $content = $this->getOutputBuffer();

        $this->response->setStatusCode(200);
        $this->response->setBody($content);

        $this->eventManager->trigger(EventTriggers::ROUTE_DISPATCHER_DISPATCH_AFTER, $route);
    }
}
