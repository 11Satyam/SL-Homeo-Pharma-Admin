{{-- Edit-distributor dialog, shared by the Distributors and Shops pages. Expects $d (Distributor). --}}
<x-erp.modal :id="'edit-dist-'.$d->id" :title="'Edit distributor · '.$d->business_name" subtitle="Business details shown on invoices and reports. The owner’s login is managed on Users & Staff." :action="route('erp.distributors.update', $d)" submit="Save changes">
  <div class="row g-3">
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-7" name="business_name" label="Business name" required maxlength="255" :value="$d->business_name"/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-5" name="area" label="Territory" maxlength="100" :value="$d->area"/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-6" name="contact_person" label="Contact person" maxlength="255" :value="$d->contact_person"/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-6" name="phone" label="Phone" type="tel" maxlength="30" :value="$d->phone"/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-7" name="email" label="Business email" type="email" :value="$d->email" help="Printed on invoices. Not the login email."/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-md-5" name="credit_limit" label="Credit limit (₹)" type="number" step="0.01" min="0" :value="$d->credit_limit"/>
    <x-erp.field :form="'edit-dist-'.$d->id" class="col-12" name="address" label="Address" maxlength="500" :value="$d->address"/>
  </div>
</x-erp.modal>
