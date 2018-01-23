<?php

namespace Drupal\apigee_edge\Entity\Form;

use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app edit forms.
 */
class DeveloperAppEditForm extends DeveloperAppCreateForm {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $entity;

  /**
   * The original developer app entity state before the submission.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $originalEntity;

  /**
   * Constructs DeveloperAppEditForm.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK Connector service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($sdk_connector, $config_factory, $entity_type_manager);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    $form = parent::form($form, $form_state);

    unset($form['#after_build']);
    $form['#tree'] = TRUE;
    $form['details']['name']['#access'] = FALSE;
    $form['details']['developerId']['#access'] = FALSE;
    $form['product']['#access'] = !isset($form['product']) ?: FALSE;

    if ($config->get('associate_apps') && $config->get('user_select')) {
      foreach ($this->entity->getCredentials() as $credential) {
        $credential_status_element = [
          '#type' => 'status_property',
          '#value' => Xss::filter($credential->getStatus()),
        ];
        $rendered_credential_status = $this->renderer->render($credential_status_element);
        $credential_title = $rendered_credential_status . ' Credential - ' . $credential->getConsumerKey();

        $form['credential'][$credential->getConsumerKey()] = [
          '#type' => 'fieldset',
          '#title' => $credential_title,
          '#collapsible' => FALSE,
        ];

        /** @var \Drupal\apigee_edge\Entity\ApiProduct[] $products */
        $products = ApiProduct::loadMultiple();
        $product_list = [];
        foreach ($products as $product) {
          $product_list[$product->id()] = $product->getDisplayName();
        }

        $multiple = $config->get('multiple_products');
        $required = $config->get('require');
        $current_products = [];
        foreach ($credential->getApiProducts() as $product) {
          $current_products[] = $product->getApiproduct();
        }

        $form['credential'][$credential->getConsumerKey()]['api_products'] = [
          '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
          '#required' => $required,
          '#options' => $product_list,
        ];

        if ($multiple) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = $current_products;
        }
        else {
          if ($required) {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = reset($current_products) ?: NULL;
          }
          else {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = reset($current_products) ?: '';
          }
        }

        if ($config->get('display_as_select')) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'select';
          $form['credential'][$credential->getConsumerKey()]['api_products']['#multiple'] = $multiple;
          $form['credential'][$credential->getConsumerKey()]['api_products']['#empty_value'] = '';
        }
        else {
          if ($multiple) {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'checkboxes';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $product_list;
          }
          else {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'radios';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $required ? $product_list : ['' => t('N/A')] + $product_list;
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save');

    $actions['delete']['#access'] = $this->entity->access('delete');
    $actions['delete']['#url'] = $this->getFormId() === 'developer_app_developer_app_edit_for_developer_form'
      ? $this->entity->toUrl('delete-form-for-developer')
      : $this->entity->toUrl('delete-form');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    $this->originalEntity = clone $this->entity;

    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $entity->setDisplayName($form_state->getValue(['details', 'displayName']));

    if ($config->get('callback_url_visible')) {
      $entity->setCallbackUrl($form_state->getValue(['details', 'callbackUrl']));
    }
    if ((bool) $config->get('description_visible')) {
      $entity->setDescription($form_state->getValue(['details', 'description']));
    }

    if ($config->get('associate_apps')) {
      foreach ($form_state->getValue(['credential']) as $consumer_key => $api_products) {
        foreach ($entity->getCredentials() as $credential) {
          if ($credential->getConsumerKey() === $consumer_key) {
            $selected_products = [];
            if ($config->get('multiple_products')) {
              foreach ($api_products['api_products'] as $api_product) {
                if ($api_product !== 0) {
                  $selected_products[] = new CredentialProduct($api_product, '');
                }
              }
            }
            else {
              if (isset($api_products['api_products']) && $api_products['api_products'] !== '') {
                $selected_products[] = new CredentialProduct($api_products['api_products'], '');
              }
            }
            $credential->setApiProducts($selected_products);
            break;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('apigee_edge.appsettings');

    $redirect_user = FALSE;

    if ($config->get('associate_apps')) {
      try {
        $dacc = new DeveloperAppCredentialController(
          $this->sdkConnector->getOrganization(),
          $this->entity->getDeveloperId(),
          $this->entity->getName(),
          $this->sdkConnector->getClient()
        );

        foreach ($this->entity->getCredentials() as $new_credential) {
          foreach ($this->originalEntity->getCredentials() as $original_credential) {
            if ($new_credential->getConsumerKey() === $original_credential->getConsumerKey()) {
              $new_api_product_names = [];
              $original_api_product_names = [];
              foreach ($new_credential->getApiProducts() as $new_api_product) {
                $new_api_product_names[] = $new_api_product->getApiproduct();
              }
              foreach ($original_credential->getApiProducts() as $original_api_product) {
                $original_api_product_names[] = $original_api_product->getApiproduct();
              }

              $product_list_changed = FALSE;
              if (array_diff($original_api_product_names, $new_api_product_names)) {
                foreach (array_diff($original_api_product_names, $new_api_product_names) as $api_product_to_remove) {
                  $dacc->deleteApiProduct($new_credential->id(), $api_product_to_remove);
                }
                $product_list_changed = TRUE;
                $redirect_user = TRUE;
              }
              if (array_diff($new_api_product_names, $original_api_product_names)) {
                $dacc->addProducts($new_credential->id(), array_values(array_diff($new_api_product_names, $original_api_product_names)));
                $product_list_changed = TRUE;
                $redirect_user = TRUE;
              }

              if ($product_list_changed) {
                drupal_set_message(t("@consumer_key credential's product list has been successfully updated.",
                  ['@consumer_key' => $new_credential->getConsumerKey()]));
              }
              break;
            }
          }
        }
      }
      catch (\Exception $exception) {
        drupal_set_message(t("Could not update <@consumer_key> credential's product list.",
          ['@consumer_key' => $new_credential->getConsumerKey()]), 'error');
        watchdog_exception('apigee_edge', $exception);
        $redirect_user = FALSE;
      }
    }

    // Update the app details after updating the product lists, because the
    // entity->save() function override every entity property.
    if ($this->entity->getDisplayName() !== $this->originalEntity->getDisplayName() ||
      $this->entity->getCallbackUrl() !== $this->originalEntity->getCallbackUrl() ||
      $this->entity->getDescription() !== $this->originalEntity->getDescription()) {
      try {
        $this->entity->save();
        drupal_set_message(t('@developer_app details have been successfully updated.',
          ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]));
        $redirect_user = TRUE;
      }
      catch (\Exception $exception) {
        drupal_set_message(t('Could not update @developer_app details.',
          ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]), 'error');
        watchdog_exception('apigee_edge', $exception);
        $redirect_user = FALSE;
      }
    }

    if ($redirect_user) {
      $form_state->setRedirectUrl($this->getRedirectUrl());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    if ($this->getFormId() === 'developer_app_developer_app_edit_for_developer_form') {
      if ($entity->hasLinkTemplate('canonical-by-developer')) {
        // If available, return the collection URL.
        return $entity->urlInfo('canonical-by-developer');
      }
      else {
        // Otherwise fall back to the front page.
        return Url::fromRoute('<front>');
      }
    }
    else {
      if ($entity->hasLinkTemplate('canonical')) {
        // If available, return the collection URL.
        return $entity->urlInfo('canonical');
      }
      else {
        // Otherwise fall back to the front page.
        return Url::fromRoute('<front>');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter('app') !== NULL) {
      $entity = $route_match->getParameter('app');
    }
    else {
      $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Edit @devAppLabel', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]);
  }

}
