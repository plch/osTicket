var PLCH = PLCH || {};

$(function() {
    bindDynamicElements();

    $(document).on("click", ".add-form-row", function(e){
        e.preventDefault();

        let template = $(this).closest(".table-form-container").find(".row-template").html();
        let table = $(this).closest(".table-form-container").find(".table-form");

        table.append(template);
        
        $(".remove-form-row", table).show();

        PLCH.RedactorInit();
    });

    $(document).on("click", ".remove-form-row:not(.disabled)", function(e){
        e.preventDefault();

        let row = $(this).closest("tr");
        let table = $(this).closest(".table-form-container").find(".table-form");

        row.remove();

        if (table.children("tbody").children("tr").length === 1) {
            $(".remove-form-row", table).hide();      
        }
    });

    $(document).on("formLoaded", function (e) {
        bindDynamicElements();
    });

    $(document).on("click", ".user-options-link", function() {
        if ($(this).parent().children("ul").is(":visible")) {
            $(this).parent().children("ul").hide();
        } else {
            $(this).parent().children("ul").show();
        }
    });

    function bindDynamicElements() {
        $("select").each(function (index) {
            let assocFieldName = $(this).data("associatedField");

            if (assocFieldName && assocFieldName !== "") {
                let associatedField = $(this).closest("tr").find("[name^='" + assocFieldName + "']");

                $(this).prop("disabled", "disabled");
                let _this = this;
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

        let elementsToToggle = {};

        $("[data-display-when]").each(function (index){
            let displayWhen = $("#" + $(this).data("displayWhen"));
            let displayWhenVal = $(this).data("displayWhenValue")
            let _this = this;

            let parentCell = $(this).closest("td");

            if (!elementsToToggle[$(this).data("displayWhen")]) {
                elementsToToggle[$(this).data("displayWhen")] = []; 
            } 

            elementsToToggle[$(this).data("displayWhen")].push(parentCell)

            displayWhen.each(toggle);
            displayWhen.on("click", toggle);

            function toggle() {
                if (((displayWhenVal === "true" || displayWhenVal === true) && $(this).prop("checked"))
                    || ((displayWhenVal === "false" || displayWhenVal === false) && !$(this).prop("checked"))
                    || displayWhenVal == $(this).val()) {
                    if (elementsToToggle[$(_this).data("displayWhen")]) {
                        for (var i=0; i < elementsToToggle[$(_this).data("displayWhen")].length; i++) {
                            elementsToToggle[$(_this).data("displayWhen")][i].show();
                        }
                    }
                } else {
                    if (elementsToToggle[$(_this).data("displayWhen")]) {
                        for (var i=0; i < elementsToToggle[$(_this).data("displayWhen")].length; i++) {
                            elementsToToggle[$(_this).data("displayWhen")][i].hide();
                        }
                    }
                }
            }
        });

        $("input[data-required-when]").each(function (index){
            let requiredWhen = $("#" + $(this).data("requiredWhen"));
            let requiredWhenVal = $(this).data("requiredWhenValue")
            let requiredLabel = $(this).closest("label");
            let _this = this;

            let parentCell = $(this).closest("td");

            if (parentCell.is(":hidden")) {
                if (!elementsToToggle[$(this).data("requiredWhen")]) {
                    elementsToToggle[$(this).data("requiredWhen")] = []; 
                } 

                elementsToToggle[$(this).data("requiredWhen")].push(parentCell)
            }

            requiredWhen.each(toggle);

            requiredWhen.on("click", toggle);

            function toggle() {
                if (((requiredWhenVal === "true" || requiredWhenVal === true) && $(this).prop("checked"))
                    || ((requiredWhenVal === "false" || requiredWhenVal === false) && !$(this).prop("checked"))
                    || requiredWhenVal == $(this).val()) {
                    requiredLabel.children("span").addClass("required");
                    requiredLabel.find("span.error").show();
                    $(_this).prop("required", true);
                    if (elementsToToggle[$(_this).data("requiredWhen")]) {
                        for (var i=0; i < elementsToToggle[$(_this).data("requiredWhen")].length; i++) {
                            elementsToToggle[$(_this).data("requiredWhen")][i].show();
                        }
                    }
                } else {
                    requiredLabel.children("span").removeClass("required");
                    requiredLabel.find("span.error").hide();
                    $(_this).prop("required", false);
                    if (elementsToToggle[$(_this).data("requiredWhen")]) {
                        for (var i=0; i < elementsToToggle[$(_this).data("requiredWhen")].length; i++) {
                            elementsToToggle[$(_this).data("requiredWhen")][i].hide();
                        }
                    }
                }
            }
        });

        $("input.dp").each(function() {
            var config = {
                numberOfMonths: 2,
                showButtonPanel: true,
                buttonImage: './images/cal.png',
                showOn:'both'
            }

            if ($(this).data("minValue")) {
                config.minDate = new Date($(this).data("minValue"));
            }

            if ($(this).data("maxValue")) {
                if ($(this).data("maxValue") === "now") {
                    config.maxDate = new Date();
                } else {
                    config.maxDate = new Date($(this).data("maxValue"));
                }
            }

            $(this).datepicker(config);
        });
    }
});


