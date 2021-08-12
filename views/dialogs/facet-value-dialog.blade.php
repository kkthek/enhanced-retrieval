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
        <p>Wählen Sie die Werte aus, die berücksichtigt werden sollen.</p>
        <table style="width: 80%">
        @foreach($values as $val)
          <div class="facet">
            <input type="checkbox" value="{{$val['id'] == $val['label'] ? $val['id'] : $val['id'].'|'.$val['label']}}" />
            {!!$val['label']!!}
          </div>
        @endforeach
        </table>
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