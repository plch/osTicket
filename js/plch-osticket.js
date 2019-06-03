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

    $(document).on("formLoaded", function (e) {
        $("select").each(function (index) {
            var assocFieldName = $(this).data("associatedField");

            if (assocFieldName && assocFieldName !== "") {
                var associatedField = $(this).closest("tr").find("[name^='" + assocFieldName + "']");

                $(this).prop("disabled", "disabled");
                var _this = this;
                associatedField.on("change", function (e) {  
                    if ($(_this).children("option[data-associated-type='" + $("option:selected", this).text() + "']").length > 0)
                    {       
                        $(_this).children("option").show();    
                        $(_this).children("option[data-associated-type!='" + $("option:selected", this).text() + "']").hide();

                        $(_this).prop("disabled", false);
                    }
                    else
                    {
                        $(_this).val("");
                        $(_this).prop("disabled", "disabled");
                    }
                });
            }
        });
    });

    $(document).on("click", ".user-options-link", function() {
        if ($(this).parent().children("ul").is(":visible")) {
            $(this).parent().children("ul").hide();
        } else {
            $(this).parent().children("ul").show();
        }
    });
});
