<!-- Modal -->
<div id="facet-value-dialog" class="modal fade" role="dialog">
  <div class="modal-dialog-fs">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Facette '{{$facetName}}'</h4>
      </div>
      <div class="modal-body">
       <p>Wählen Sie die Werte aus, die berücksichtigt werden sollen.</p>
       
       <table style="width: 80%">
       
       @foreach($values as $val)
       		<div class="facet"><input type="checkbox" value="{{$val['id'] == $val['label'] ? $val['id'] : $val['id'].'|'.$val['label']}}" /> {{$val['label']}}</div>
       @endforeach
       
       </table>     
      </div>
      <div class="modal-footer">
      	<button type="button" action="select-all" class="btn btn-default" style="float: left">Alle auswählen</button>
      	<button type="button" action="select-none" class="btn btn-default" style="float: left">Keine auswählen</button>
        <button type="button" action="ok" class="btn btn-default">OK</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
      </div>
    </div>

  </div>
</div>