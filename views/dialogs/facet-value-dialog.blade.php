<!--
  Modal dialog from Bootstrap4 
  https://getbootstrap.com/docs/4.3/components/modal/
-->
<div id="fs-facet-value-dialog" class="modal fade" role="dialog" tabindex="-1">
  <div class="modal-dialog modal-dialog-fs" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Facette '{{$facetName}}'</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="fsgFacetDialogMetadata" toremove="{{$toRemove}}" style="display: none;"></div>
        <p>Wählen Sie die Werte aus, die berücksichtigt werden sollen.</p>
        <span>Filter</span><input type="text" id="search-field" style="width: 100%; margin-top:3px" />
        <div style="height: 400px; margin-top: 10px;">
        <table style="width: 80%; overflow: auto">
        @foreach($values as $val)
          <div class="facet">
            <input type="checkbox" i
                   isLeaf="true"
                   filtervalue="{{mb_strtolower($val['label'])}}"
                   value="{{$val['facetValue']}}"
                   propertyfacet="{{$val['propertyFacet']}}"
                   property="{{$val['property']}}"/>
            {!!$val['label']!!}
          </div>
        @endforeach
        </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" action="select-all" class="btn btn-secondary" style="float:left">Alle auswählen</button>
        <button type="button" action="select-none" class="btn btn-secondary" style="float:left">Keine auswählen</button>
        <button type="button" action="ok" class="btn btn-primary">OK</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
      </div>
    </div>
  </div>
</div>