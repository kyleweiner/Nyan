# Nyan #

Nyan is an ExpressionEninge plug-in that displays a list of categories in a tag cloud format, where each category is assigned a CSS class based on its popularity.

## Installation

* Copy the `nyan` folder to your `/system/expressionengine/third_party/` directory.

## Features

* No forced inline styles: use your own CSS.
* Define your popularity styles using 1 or 100 classes. You decide.
* Display the entry count next to each category.
* Set the minimum number of entries a category needs to appear in the results.
* Limit your results to a maximum number of categories.
* Use any combination of categories, irrespective of channel.

## Parameters

<table>
<tr>
	<th>Parameter</th>
	<th>Type</th>
	<th>Default</th>
	<th>Description</th>
	<th>Required</th>
</tr>
<tr>
	<td>cat_id</td>
	<td>string</td>
	<td></td>
	<td>Comma or pipe delimited string of category group ids.</td>
	<td>Yes</td>
</tr>
<tr>
	<td>class</td>
	<td>string</td>
	<td></td>
	<td>Class(es) for the outermost list container.</td>
	<td></td>
</tr>
<tr>
	<td>id</td>
	<td>string</td>
	<td></td>
	<td>ID for the outermost list container.</td>
	<td></td>
</tr>
<tr>
	<td>debug</td>
	<td>yes|no</td>
	<td>no</td>
	<td>Set to "yes" to enable debugging.</td>
	<td></td>
</tr>
<tr>
	<td>limit</td>
	<td>int</td>
	<td></td>
	<td>Maximum number of categories to show.</td>
	<td></td>
</tr>
<tr>
	<td>min_count</td>
	<td>int</td>
	<td>0</td>
	<td>Minimum number of entries a category needs to appear in the results.</td>
	<td></td>
</tr>
<tr>
	<td>order</td>
	<td>abc|pop</td>
	<td>pop</td>
	<td>Set to 'abc' for alphabetical or 'pop' for popularity / ordering by entry count.</td>
	<td></td>
</tr>
<tr>
	<td>parent_only</td>
	<td>yes|no</td>
	<td>no</td>
	<td>Set to "yes" to return only parent categories; no sub-categories will be displayed.</td>
	<td></td>
</tr>
<tr>
	<td>scale</td>
	<td>string</td>
	<td>'not-popular, mildly-popular, popular, very-popular, super-popular'</td>
	<td>Comma or pipe delimited string of classes ordered from least to most popular.</td>
	<td></td>
</tr>
<tr>
	<td>sort</td>
	<td>asc|desc</td>
	<td>desc</td>
	<td>Set to "asc" or "desc" (optional, default is 'desc'.</td>
	<td></td>
</tr>
</table>

## Single Variables

<table>
<tr>
	<th>Variable</th>
	<th>Description</th>
</tr>
<tr>
	<td>{cat_id}</td>
	<td>The ID of the category.</td>
</tr>
<tr>
	<td>{cat_name}</td>
	<td>The name of the category.</td>
</tr>
<tr>
	<td>{cat_url_title}</td>
	<td>The URL title of the category.</td>
</tr>
<tr>
	<td>{cat_entry_count}</td>
	<td>The number of entries the category is used in.</td>
</tr>
<tr>
	<td>{cat_weight}</td>
	<td>The CSS class assigned to the category as measured by its popularity.</td>
</tr>
<tr>
	<td>{parent_id}</td>
	<td>The parent ID for the category.</td>
</tr>
</table>

## Additional Single Variables

<table>
<tr>
	<th>Variable</th>
	<th>Description</th>
</tr>
<tr>
	<td>{count}</td>
	<td>The count out of the current category.</td>
</tr>
<tr>
	<td>{no_results}</td>
	<td>Conditional (e.g. {if no_results}No Results!{/if}) for displaying a message when no data is returned.</td>
</tr>
<tr>
	<td>{switch=''}</td>
	<td>Rotates through any number of pipe delimited values.</td>
</tr>
<tr>
	<td>{total_results}</td>
	<td>The total amount of categories returned.</td>
</tr>
</table>

## Examples

### Basic usage

	{exp:nyan cat_id="1"}
	<li class="{cat_weight}">{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Multiple categories

	{exp:nyan cat_id="1|2|3"}
	<li class="{cat_weight}">{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Custom classes for category weight

	{exp:nyan cat_id="1" scale="not-popular, popular, very-popular"}
	<li class="{cat_weight}">{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Limit categories by popularity

This example will only return categories that are used by 2 or more entires.

	{exp:nyan cat_id="1" min_count="2"}
	<li class="{cat_weight}">{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Limit categories returned

This example will display only 2 categories.

	{exp:nyan cat_id="1" limit="2"}
	<li class="{cat_weight}">{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Flat list of categories with entry counts

	{exp:nyan cat_id="1"}
	<li>{cat_name} ({cat_entry_count})</li>
	{/exp:nyan}

### Additional tags example

	{exp:nyan cat_id="1" id="my-id" class="my-class"}
	<li class="{cat_weight} {switch='odd meow|even meow-meow'}">
		<a href="category/{cat_url_title}">{count} of {total_results}: {cat_name} ({cat_entry_count})</a>
		{if no_results}Sorry, no results.{/if}
	</li>
	{/exp:nyan}

### Example with CSS

	{exp:nyan cat_id="1" class="categories"}
	<li class="{cat_weight}">
		<span>{cat_entry_count} entries in</span> <a href="category/{cat_url_title}">{cat_name}</a>
		{if no_results}There are no categories to display.{/if}
	</li>
	{/exp:nyan}

The following CSS will render a traditional tag cloud style list of categories and assumes the "class" parameter is set to "categories":

	.categories li { display: inline; }
		
	/* Hide only visually, but have it available for screenreaders: h5bp.com/v */
	.categories span {
		border: 0; 
		clip: rect(0 0 0 0); 
		height: 1px;
		margin: -1px; 
		overflow: hidden; 
		padding: 0; 
		position: absolute; 
		width: 1px; }
	
	/* Nyan classes */
	.categories .not-popular { font-size: 1em; opacity: .2; }
	.categories .mildly-popular { font-size: 1.4em; opacity: .4; }
	.categories .popular { font-size: 1.8em; opacity: .6; }
	.categories .very-popular { font-size: 2.2em; opacity: .8; }
	.categories .super-popular { font-size: 2.6em; opacity: .95; }

## Change Log

### v1.0.1

* Initial release