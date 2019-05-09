var PLCH = PLCH || {};

$(function() {
    $(document).on("click", ".add-form-row", function(e){
        e.preventDefault();

        var template = $(this).closest(".table-form-container").find(".row-template").html();
        var table = $(this).closest(".table-form-container").find(".table-form");

        table.append(template);
        
        $(".remove-form-row", table).show();

        PLCH.RedactorInit();
    });

    $(document).on("click", ".remove-form-row:not(.disabled)", function(e){
        e.preventDefault();

        var row = $(this).closest("tr");
        var table = $(this).closest(".table-form-container").find(".table-form");

        row.remove();

        if (table.children("tbody").children("tr").length === 1) {
            $(".remove-form-row", table).hide();      
        }
    });
});
