<?php
declare(strict_types=1);

use App\Action\Auth\SignInAction;
use App\Action\Auth\SignInSubmitAction;
use App\Action\Auth\SignOutAction;
use App\Action\Auth\SignUpAction;
use App\Action\Auth\SignUpSubmitAction;
use App\Action\City\CitySearchAction;
use App\Action\Dashboard\DashboardAction;
use App\Action\Events\BrowseEventsAction;
use App\Action\Events\EventCreateAction;
use App\Action\Events\EventCreateSubmitAction;
use App\Action\Events\EventDeleteAction;
use App\Action\Events\EventEditAction;
use App\Action\Events\EventEditSubmitAction;
use App\Action\Events\EventIcalAction;
use App\Action\Events\EventRsvpAction;
use App\Action\Events\EventLegacyRedirectAction;
use App\Action\Events\EventViewAction;
use App\Action\Discussions\DiscussionCreateAction;
use App\Action\Discussions\DiscussionCreateSubmitAction;
use App\Action\Discussions\DiscussionDeleteAction;
use App\Action\Discussions\DiscussionListAction;
use App\Action\Discussions\DiscussionViewAction;
use App\Action\Discussions\ReplyCreateAction;
use App\Action\Discussions\ReplyDeleteAction;
use App\Action\Groups\GroupCreateAction;
use App\Action\Groups\GroupCreateSubmitAction;
use App\Action\Groups\GroupDeleteAction;
use App\Action\Groups\GroupEditAction;
use App\Action\Groups\GroupEditSubmitAction;
use App\Action\Groups\GroupJoinAction;
use App\Action\Groups\GroupLeaveAction;
use App\Action\Groups\GroupLegacyRedirectAction;
use App\Action\Groups\GroupLinkAddAction;
use App\Action\Groups\GroupLinkDeleteAction;
use App\Action\Groups\GroupListAction;
use App\Action\Groups\GroupFeedAction;
use App\Action\Groups\GroupMembersAction;
use App\Action\Groups\GroupViewAction;
use App\Action\Groups\PastEventsAction;
use App\Action\Home\HomeAction;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;

return function (App $app): void {
    $app->get("/", HomeAction::class);
    $app->get("/privacy-policy", function (
        Request $request,
        Response $response,
        array $args,
    ) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);

        return $twig->render($response, "privacy-policy.twig");
    });

    // Auth
    $app->get("/signin", SignInAction::class);
    $app->post("/signin", SignInSubmitAction::class);
    $app->get("/signup", SignUpAction::class);
    $app->post("/signup", SignUpSubmitAction::class);
    $app->post("/signout", SignOutAction::class);

    // Dashboard (auth-gated)
    $app->get("/dashboard", DashboardAction::class)->add(AuthMiddleware::class);

    // Groups
    $app->get("/groups", GroupListAction::class);
    $app->get("/groups/create", GroupCreateAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post("/groups/create", GroupCreateSubmitAction::class)->add(
        AuthMiddleware::class,
    );

    // Legacy numeric ID redirect (301 to slug URL)
    $app->get("/groups/{id:[0-9]+}", GroupLegacyRedirectAction::class);

    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}", GroupViewAction::class);
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/join", GroupJoinAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/leave", GroupLeaveAction::class)->add(
        AuthMiddleware::class,
    );
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/edit", GroupEditAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/edit", GroupEditSubmitAction::class)->add(
        AuthMiddleware::class,
    );
    $app->delete("/groups/{slug:[a-z0-9][a-z0-9-]*}/delete", GroupDeleteAction::class)->add(
        AuthMiddleware::class,
    );
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/feed.xml", GroupFeedAction::class);
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/members", GroupMembersAction::class);
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/past-events", PastEventsAction::class);
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/links", GroupLinkAddAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post(
        "/groups/{slug:[a-z0-9][a-z0-9-]*}/links/{link_id:[0-9]+}/delete",
        GroupLinkDeleteAction::class,
    )->add(AuthMiddleware::class);

    // Discussions
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions", DiscussionListAction::class);
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/create", DiscussionCreateAction::class)->add(AuthMiddleware::class);
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/create", DiscussionCreateSubmitAction::class)->add(AuthMiddleware::class);
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/{topic_id:[0-9]+}", DiscussionViewAction::class);
    $app->delete("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/{topic_id:[0-9]+}", DiscussionDeleteAction::class)->add(AuthMiddleware::class);
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/{topic_id:[0-9]+}/replies", ReplyCreateAction::class)->add(AuthMiddleware::class);
    $app->delete("/groups/{slug:[a-z0-9][a-z0-9-]*}/discussions/{topic_id:[0-9]+}/replies/{reply_id:[0-9]+}", ReplyDeleteAction::class)->add(AuthMiddleware::class);

    // Events (nested under groups)
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/create", EventCreateAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/create", EventCreateSubmitAction::class)->add(
        AuthMiddleware::class,
    );
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}", EventViewAction::class);
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}/ical", EventIcalAction::class);
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}/rsvp", EventRsvpAction::class)->add(
        AuthMiddleware::class,
    );
    $app->get("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}/edit", EventEditAction::class)->add(
        AuthMiddleware::class,
    );
    $app->post("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}/edit", EventEditSubmitAction::class)->add(
        AuthMiddleware::class,
    );
    $app->delete("/groups/{slug:[a-z0-9][a-z0-9-]*}/events/{id:[0-9]+}/delete", EventDeleteAction::class)->add(
        AuthMiddleware::class,
    );

    // Browse events (cross-group discovery page)
    $app->get("/events", BrowseEventsAction::class);

    // Legacy event URL redirects (301 to group-scoped URLs)
    $app->get("/events/{id:[0-9]+}", EventLegacyRedirectAction::class);

    // HTMX endpoints
    $app->get("/city-search", CitySearchAction::class);
};
