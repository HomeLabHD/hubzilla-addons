<div id="contact-slider" class="slider mb-3"><input id="contact-range" type="text" name="fake-closeness" value="{{$val}}" /></div>
<script>
    $(document).ready(function () {
        makeContactSlider();
    });

    function makeContactSlider() {
        $("#contact-range").jRange({from: {{$min|default:'0'}}, to: 99, step: 1, scale: [{{$labels}}], width: '98%', showLabels: false, onstatechange: function (v) {
                sliderChanged(v); }});
    }
</script>
