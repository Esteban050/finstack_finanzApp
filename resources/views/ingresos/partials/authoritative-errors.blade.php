@if($errors->has('ingreso') || $errors->has('fuente'))<p class="ui-error" role="alert"><x-ui.safe-message :value="$errors->first('ingreso') ?: $errors->first('fuente')" /></p>@endif
