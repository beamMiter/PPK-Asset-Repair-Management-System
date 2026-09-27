<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A search or filter in the URL is a single word: `?q=printer`. Somebody who sends `?q[]=x` (or `?status[a]=b`) turns it into an array,
 * and every controller that then reads it as text ("Array to string conversion") answered 500. No page or API call of the app takes an
 * array in a query string (its arrays - file lists, checkboxes - are POSTed), so on a GET / HEAD an array-valued parameter is dropped
 * here, once, and the request goes on as if it had not been sent: the default list.
 */
class IgnoreArrayQuery
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $query = $request->query->all();
            $scalars = array_filter($query, static fn ($value) => ! is_array($value));

            if (count($scalars) !== count($query)) {
                $request->query->replace($scalars);
            }
        }

        return $next($request);
    }
}
