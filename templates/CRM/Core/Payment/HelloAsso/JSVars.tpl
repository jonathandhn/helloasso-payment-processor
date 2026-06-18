{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{* Manually create the CRM.vars.helloassoPayment here for drupal webform because \Civi::resources()->addVars() does not always work in that context *}
{literal}
<script type="text/javascript">
  CRM.$(function($) {
    $(document).ready(function() {
      CRM.vars.helloassoPayment = {/literal}{$helloassoJSVars|@json_encode nofilter}{literal};
    });
  });
</script>
{/literal}
