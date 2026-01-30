{* Scarcity Banner Template - Optimized for FO and Live Preview *}
{assign var=b value=$psscarcity_band}
<div class="alert alert-warning scarcity"
     data-psscarcity="1"
     role="status"
     aria-live="polite"
     {if $b == null}style="display:none"{/if}
     data-band="{$b|escape:'html'}"
     data-qty="{$psscarcity_qty|intval}"
     data-msg-one="{$psscarcity_msg_one|escape:'html':'UTF-8'}"
     data-msg-10="{$psscarcity_msg_10|escape:'html':'UTF-8'}"
     data-msg-20="{$psscarcity_msg_20|escape:'html':'UTF-8'}"
     data-limit-lt10="{$psscarcity_limit_lt10|intval}"
     data-limit-lt20="{$psscarcity_limit_lt20|intval}">
     
  {* Render the final message with %count% replaced by quantity *}
  {if $psscarcity_final}
    {$psscarcity_final nofilter}
  {/if}
</div>

{* Optional: Include CSS class for custom styling *}
<style>
  {if isset($psscarcity_custom_css)}
    {$psscarcity_custom_css nofilter}
  {/if}
</style>
