        <div class="mt-4 space-y-6">
            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>

                @include('maintenance.requests.partials._asset_info')

                @include('maintenance.requests.partials._issue_details')
            </div>

            <div class="border-t {{ $line }}"></div>

            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
                @include('maintenance.requests.partials._reporter_info')
                @include('maintenance.requests.partials._attachments')
            </div>

            <div class="border-t {{ $line }}"></div>

            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
                @include('maintenance.requests.partials._assigned_team')
                @include('maintenance.requests.partials._operation_log')
            </div>

            <div class="border-t {{ $line }}"></div>

            @include('maintenance.requests.partials._sla_info')
        </div>
