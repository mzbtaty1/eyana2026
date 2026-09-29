{{--
    Shared by the booking and program forms: shows only the fields of a service row's type
    (TourismBookingItem::FIELDS). Groups inside the row are marked .tb-f[data-types="a,b"]; a
    group of another type is hidden and its inputs disabled (never sent -- the server clears
    those fields anyway). tourismServiceType(tr, clear): clear = empty the hidden fields (on a
    type change by the user). Also sets the quantity hint (.tb-qty-hint) of the type.
--}}
<script>
   window.TOURISM_FIELDS = @json(\App\Models\TourismBookingItem::FIELDS);
   window.tourismServiceType = function (tr, clear) {
      var type = tr.querySelector('.tb-type').value;
      tr.querySelectorAll('.tb-f').forEach(function (g) {
         var on = g.dataset.types.split(',').indexOf(type) !== -1;
         g.classList.toggle('d-none', !on);
         g.querySelectorAll('input, select').forEach(function (el) {
            el.disabled = !on;
            // entered values only (a select or a fixed hidden value keeps its value)
            if (!on && clear && el.tagName === 'INPUT' && el.type !== 'hidden') { el.value = ''; }
         });
      });
      var hint = tr.querySelector('.tb-qty-hint');
      if (hint) { hint.textContent = (window.TOURISM_FIELDS[type] || window.TOURISM_FIELDS.other).quantity; }
      return type;
   };
</script>
