{**
 * organizingTeam.tpl
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * About the Conference index.
 *
 * $Id$
 *}
{assign var="pageTitle" value="about.organizingTeam"}
{include file="common/header.tpl"}

{foreach from=$groups item=group}
<h4>{$group->getGroupTitle()}</h4>
{assign var=groupId value=$group->getGroupId()}
{assign var=members value=$teamInfo[$groupId]}

{foreach from=$members item=member}
	{assign var=user value=$member->getUser()}
	<a href="javascript:openRTWindow('{url op="organizingTeamBio" path=$user->getUserId()}')">{$user->getFullName()|escape}</a>{if $user->getAffiliation()}, {$user->getAffiliation()|escape}{/if}{if $user->getCountry()}{assign var=countryCode value=$user->getCountry()}{assign var=country value=$countries.$countryCode}, {$country|escape}{/if}
	<br />
{/foreach}
{/foreach}

{include file="about/conferenceSponsorship.tpl"}

{include file="common/footer.tpl"}
