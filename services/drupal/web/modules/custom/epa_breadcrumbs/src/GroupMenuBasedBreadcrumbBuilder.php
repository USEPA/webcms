<?php

namespace Drupal\epa_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\node\NodeInterface;


/**
 * {@inheritdoc}
 */
class GroupMenuBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The configuration object generator.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The menu active trail interface.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The menu link manager interface.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The admin context generator.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The caching backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * The locking backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The Menu Breadcrumbs configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The group menu where the current page lives.
   *
   * @var string
   */
  private $menuName;

  /**
   * The menu trail leading to this match.
   *
   * @var string
   */
  private $menuTrail;

  /**
   * Content language code (used in both applies() and build()).
   *
   * @var string
   */
  private $contentLanguage;

  /**
   * The group this breadcrumb is being built fore.
   *
   * @var GroupInterface
   */
  private $group;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    MenuActiveTrailInterface $menu_active_trail,
    MenuLinkManagerInterface $menu_link_manager,
    AdminContext $admin_context,
    TitleResolverInterface $title_resolver,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    CacheBackendInterface $cache_menu,
    LockBackendInterface $lock
  ) {
    $this->configFactory = $config_factory;
    $this->menuActiveTrail = $menu_active_trail;
    $this->menuLinkManager = $menu_link_manager;
    $this->adminContext = $admin_context;
    $this->titleResolver = $title_resolver;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheMenu = $cache_menu;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {

    // Don't breadcrumb the admin pages:
    if ( $this->adminContext->isAdminRoute($route_match->getRouteObject())) {
      return FALSE;
    }

    // No route name means no active trail:
    $route_name = $route_match->getRouteName();
    if (!$route_name) {
      return FALSE;
    }

    $node_object = $route_match->getParameters()->get('node');

    if ($node_object == NULL) {
      return FALSE;
    }

    // Make sure menus are selected, and breadcrumb text strings, are displayed
    // in the content rather than the (default) interface language:
    $this->contentLanguage = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    // Get the group from the current node.
    $group_content = GroupContent::loadByEntity($node_object);

    if (!empty($group_content)) {
      $group_content = reset($group_content);
    }
    else {
      // No group content, no menu.
      return FALSE;
    }

    $group = $group_content->getGroup();
    $menus = group_content_menu_get_menus_per_group($group);
    $menu = reset($menus);
    $menu_id = GroupContentMenuInterface::MENU_PREFIX . $menu->id();

    if ($menu_id) {
      $this->menuTrail = [];
      $trail_ids = $this->menuActiveTrail->getActiveTrailIds($menu_id);

      $trail_ids = array_filter($trail_ids);
      if ($trail_ids) {
        $this->menuTrail = $trail_ids;
      }

      $this->menuName = $menu_id;
      $this->group = $group;
      return TRUE;
    }

    // No more menus to check...
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    // Breadcrumbs accumulate in this array, with lowest index being the root
    // (i.e., the reverse of the assigned breadcrumb trail):
    $links = [];
    // (https://www.drupal.org/docs/develop/standards/coding-standards#array)
    //
    if ($this->languageManager->isMultilingual()) {
      $breadcrumb->addCacheContexts(['languages:language_content']);
    }

    // Changing the <front> page will invalidate any breadcrumb generated here:
    $site_config = $this->configFactory->get('system.site');
    $breadcrumb->addCacheableDependency($site_config);


    // Add contexts for all of the menu and the current path.
    $breadcrumb->addCacheContexts(['route.menu_active_trails:' . $this->menuName]);
    $breadcrumb->addCacheContexts(['url.path']);

    // Generate basic breadcrumb trail from active trail.
    // Keep same link ordering as Menu Breadcrumb (so also reverses menu trail)
    foreach (array_reverse($this->menuTrail) as $id) {
      $plugin = $this->menuLinkManager->createInstance($id);

      // Skip items that have an empty URL.
      if (empty($plugin->getUrlObject()->toString())) {
        continue;
      }

      // Skip items that are disabled in the menu.
      if (!$plugin->isEnabled()) {
        continue;
      }

      // Add cachability dependency to the group itself.
      $breadcrumb->addCacheableDependency($this->group);

      $links[] = Link::fromTextAndUrl($plugin->getTitle(), $plugin->getUrlObject());
      $breadcrumb->addCacheableDependency($plugin);
      // In the last line, MenuLinkContent plugin is not providing cache tags.
      // Until this is fixed in core add the tags here:
      if ($plugin instanceof MenuLinkContent) {
        $uuid = $plugin->getDerivativeId();
        $entities = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['uuid' => $uuid]);
        if ($entity = reset($entities)) {
          $breadcrumb->addCacheableDependency($entity);
        }
      }
    }

    // Create a breadcrumb for the Web Area's homepage if it's published.
    /** @var \Drupal\node\Entity\Node $web_area_home */
    $web_area_home = $this->group->get('field_homepage')->entity;
    $node = $this->currentRequest->get('node');
    if ($web_area_home) {
      if ($node && $node->bundle() === 'news_release') {
        // Add the news release search page instead of the web area homepage for News Release nodes.
        $links[] = Link::createFromRoute(t('News Releases'), 'view.search_news_releases.page_1');
      }
      else {
        $breadcrumb->addCacheableDependency($web_area_home);
        if ($web_area_home->isPublished()) {
          $web_area_home_link =  Link::createFromRoute($this->group->label(), $web_area_home->toUrl()->getRouteName(), $web_area_home->toUrl()->getRouteParameters());
          array_unshift($links, $web_area_home_link);
        }
      }
    }

    // Create a breadcrumb for <front>.
    $langcode = $this->contentLanguage;
    $label = $this->t('Home', [], ['langcode' => $langcode]);
    $home_link = Link::createFromRoute($label, '<front>');

    // Add home link to the beginning of the breadcrumb trail.
    array_unshift($links, $home_link);

    /** @var \Drupal\Core\Link $last */
    $last = end($links);
    // Check if the last link is the current URL. If so, remove.
    if ($last->getUrl()->toString() == Url::fromRoute('<current>')->toString()) {
      array_pop($links);
    }

    // Per https://forumone.atlassian.net/browse/EPAD8-2411 we want to limit the
    // breadcrumbs to a max of 5 (Home -> Web Area Homepage -> Parent -> Child -> Grand Child)
    if (count($links) > 5) {
      array_splice($links, 5);
    }

    return $breadcrumb->setLinks($links);
  }

  /**
   * The getter function for $menuName property.
   *
   * @return string
   *   The menu name.
   */
  public function getMenuName() {
    return $this->menuName;
  }

  /**
   * The setter function for $menuName property.
   *
   * @param string $menu_name
   *   The menu name.
   */
  public function setMenuName($menu_name) {
    $this->menuName = $menu_name;
  }

  /**
   * The getter function for $menuTrail property.
   *
   * @return string
   *   The menu trail.
   */
  public function getMenuTrail() {
    return $this->menuTrail;
  }

  /**
   * The setter function for $menuTrail property.
   *
   * @param string $menu_trail
   *   The menu trail.
   */
  public function setMenuTrail($menu_trail) {
    $this->menuTrail = $menu_trail;
  }

  /**
   * The getter function for $contentLanguage property.
   *
   * @return string
   *   The content language.
   */
  public function getContentLanguage() {
    return $this->contentLanguage;
  }

  /**
   * The setter function for $contentLanguage property.
   *
   * @param string $contentLanguage
   *   The content language.
   */
  public function setContentLanguage($contentLanguage) {
    $this->contentLanguage = $contentLanguage;
  }

}
