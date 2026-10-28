// Local item/supplier completion; no framework dependency.
function BComplete(element, max) { this.initialize(element, max); }
function escapeCompletionPattern(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
BComplete.prototype =
{
    MAX_VISIBLE : 8,
    TIMER_TICK : 10,
    CANCEL_SUBMISSION_TIMEOUT : 10,

    initialize : function(element,max)
    {
        if(max)
        {
            this.MAX_VISIBLE = max;
        }

        this.data = new Array();
        this.element = typeof element === "string" ? document.getElementById(element) : element;
        if(!this.element)
        {
            throw("BComplete: The specified <input> element does not exist.");
        }

        this.element.setAttribute("autocomplete","off");
        this.element.classList.add("bcomplete-field");

        this.visible = false;
        this.cancelSubmit = false;
        this.scroll = 0;
        this.selectedIndex = -1;
        this.matches = new Array();

        this.popup = document.createElement("div");
        this.popup.style.display = "none";
        this.popup.className = "bcomplete-popup";
        document.body.appendChild(this.popup);

        this.upButton = document.createElement("div");
        this.upButton.className = "up-button";
        this.popup.appendChild(this.upButton);

        this.listItems = new Array();
        for(var i=0;i<this.MAX_VISIBLE;i++)
        {
            var item = document.createElement("div");
            this.listItems[i] = item ;
            item.className = "item";
            this.popup.appendChild(item);
            item.autocomplete = this;
            item.number = i;

            item.onclick = this.onItemClick;
            item.onmouseover = this.onItemOn;
            item.onmouseout = this.onItemOff;
        }

        this.downButton = document.createElement("div");
        this.downButton.className = "down-button";
        this.popup.appendChild(this.downButton);

        this.element.addEventListener("input", this.onTick.bind(this));
        this.element.addEventListener("keydown", this.onKeyDown.bind(this));
        this.upButton.addEventListener("click", this.onUpButton.bind(this));
        this.downButton.addEventListener("click", this.onDownButton.bind(this));
        document.addEventListener("click", this.onWindowClick.bind(this));

        this.onTick = this.onTick.bind(this);
        this.onSubmit = this.onSubmit.bind(this);

        // look for parent <form>
        var parentForm = this.element.parentNode;
        while(parentForm)
        {
            if(parentForm.tagName && parentForm.tagName.toLowerCase() == "form")
                break;

            parentForm = parentForm.parentNode;
        }

        // capture submit event for parent <form>
        var me = this;
        if(parentForm)
        {
            var oldHandler = parentForm.onsubmit;
            parentForm.onsubmit = function()
            {
                if(oldHandler)
                    return (oldHandler() && me.onSubmit());
                else
                    return me.onSubmit();
            };
        }
    },

    addItem : function(item)
    {
        this.data[this.data.length] = item;
        this.data.sort();
    },

    setData : function(data)
    {
        this.data = data;
        data.sort();
    },

    loadData : function(url)
    {
        var me = this;
        return fetch(url, {credentials: "same-origin"})
            .then(function(response) {
                if (!response.ok) throw new Error("BComplete: Dictionary request failed.");
                return response.text();
            })
            .then(function(text) {
                var data = JSON.parse(text);
                if (!Array.isArray(data) || !data.every(function(item) { return typeof item === "string"; }))
                    throw new Error("BComplete: Invalid data format.");
                me.setData(data);
                if (document.activeElement === me.element) me.onTick();
            })
            .catch(function(error) {
                // Leave manual entry usable when the dictionary is unavailable.
                me.hide();
                console.error(error);
            });
    },

    findMatches : function(text)
    {
        var matches = new Array();
        var expression = new RegExp("^"+escapeCompletionPattern(text),"i");

        for(var i=0;i<this.data.length;i++)
        {
            if(this.data[i].match(expression))
            {
                matches[matches.length] = this.data[i];
            }
        }

        return matches;
    },

    temporarilyDisableSubmission : function()
    {
        this.cancelSubmit = true;
        var me = this;
        var revert = function()
        {
            me.cancelSubmit = false;
        };

        setTimeout(revert,this.CANCEL_SUBMISSION_TIMEOUT);
    },

    onWindowClick : function(event)
    {
        var element = event.target;

        var parent = element;
        while(parent)
        {
            if(parent == this.element || parent == this.popup ||
               parent == this.showAllButton)
            {
                return;
            }

            parent = parent.parentNode;
        }

        this.hide();
    },

    onUpButton : function(event)
    {
        this.selectedIndex = -1;
        this.scroll--;
        if(this.scroll < 0)
            this.scroll = 0;

        this.show();
        event.preventDefault();
        event.stopPropagation();
        this.element.focus();
    },

    onDownButton : function(event)
    {
        this.selectedIndex = -1;
        this.scroll++;
        if(this.scroll > (this.matches.length - this.MAX_VISIBLE))
            this.scroll = Math.max(0, this.matches.length - this.MAX_VISIBLE);

        this.show();
        event.preventDefault();
        event.stopPropagation();
        this.element.focus();
    },

    onKeyDown : function(event)
    {
        if (event.isComposing) return;
        if (event.key === "Enter") {
            // Prevent implicit form submission; the page keyup handler advances
            // from item to quantity/rate, and quantity Enter adds the line.
            event.preventDefault();
            if (this.visible) {
                if (this.selectedIndex === -1) this.selectedIndex = this.scroll;
                this.select();
            }
        } else if (event.key === "Tab" && this.visible) {
            if (this.selectedIndex !== -1) this.select();
            else this.hide();
        } else if (event.key === "Escape") {
            this.hide();
        } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            if (!this.visible) this.matches = this.findMatches(this.element.value);
            if (!this.matches.length) return;
            var direction = event.key === "ArrowDown" ? 1 : -1;
            this.selectedIndex = Math.max(0, Math.min(this.matches.length - 1, this.selectedIndex + direction));
            if (this.selectedIndex < this.scroll) this.scroll = this.selectedIndex;
            if (this.selectedIndex >= this.scroll + this.MAX_VISIBLE)
                this.scroll = this.selectedIndex - this.MAX_VISIBLE + 1;
            this.show();
        }
    },

    showAll : function()
    {
        this.matches = this.findMatches('');
        this.element.focus();
        this.show();
    },

    onTick : function()
    {
        this.selectedIndex = -1;
        this.scroll = 0;
        if(this.element.value != '')
        {
            this.matches = this.findMatches(this.element.value);
            if(this.matches.length > 0)
                this.show();
            else
                this.hide();
        }
        else
        {
            this.hide();
        }
    },

    onSubmit : function()
    {
        if(this.cancelSubmit)
        {
            this.cancelSubmit = false;
            return false;
        }
        else
        {
            return true;
        }
    },

    onItemOn : function()
    {
        for(var i=0;i<this.autocomplete.MAX_VISIBLE;i++)
            this.autocomplete.listItems[i].classList.remove("selected");

        this.classList.add("selected");
        this.autocomplete.selectedIndex = this.number;
    },

    onItemOff : function()
    {
        this.classList.remove("selected");
        this.autocomplete.selectedIndex = -1;
    },

    onItemClick : function()
    {
        this.autocomplete.selectedIndex = this.number;
        this.autocomplete.select();
    },

    show : function()
    {
        if(this.matches.length <= 0)
            return ;

        var text = this.element.value;
        var expression = new RegExp("("+escapeCompletionPattern(text)+")","i");

        if(this.scroll > 0)
            this.upButton.classList.remove("disabled")
        else
            this.upButton.classList.add("disabled")

        if((this.scroll + this.MAX_VISIBLE) < this.matches.length)
            this.downButton.classList.remove("disabled")
        else
            this.downButton.classList.add("disabled")

        for(var i=0;i<this.MAX_VISIBLE;i++)
        {
            if(this.matches[i+this.scroll])
            {
                var text = this.matches[i+this.scroll];
                var item = this.listItems[i];
                item.textContent = "";
                var match = expression.exec(text);
                if (match && match[0]) {
                    item.appendChild(document.createTextNode(text.slice(0, match.index)));
                    var strong = document.createElement("strong");
                    strong.textContent = match[0];
                    item.appendChild(strong);
                    item.appendChild(document.createTextNode(text.slice(match.index + match[0].length)));
                } else item.textContent = text;
                this.listItems[i].number = i + this.scroll;
                this.listItems[i].value = this.matches[i+this.scroll];

                if(this.selectedIndex == (this.scroll + i))
                    this.listItems[i].classList.add("selected");
                else
                    this.listItems[i].classList.remove("selected");

                this.listItems[i].style.display = "block";
            }
            else
            {
                this.listItems[i].style.display = "none";
            }
        }

        this.visible = true;
        this.popup.style.display = "block";
        this.setPopupPosition();
    },

    setPopupPosition : function()
    {
        var rect = this.element.getBoundingClientRect();
        var position = [rect.left + window.scrollX, rect.top + window.scrollY];
        var scrollY = document.body.scrollTop ?
            document.body.scrollTop : document.documentElement.scrollTop;
        var viewHeight = (navigator.userAgent.toLowerCase().indexOf("safari") != -1 &&
            window.innerHeight) ? window.innerHeight :
                document.documentElement.clientHeight;

        this.popup.style.width = (this.element.offsetWidth - 2) + "px";
        this.popup.style.left = position[0] + "px";

        var popupTop = position[1] + this.element.offsetHeight;
        if((popupTop + this.popup.offsetHeight > scrollY + viewHeight) &&
           (position[1] - this.popup.offsetHeight > scrollY))
        {
            popupTop = position[1] - this.popup.offsetHeight;
        }

        this.popup.style.top = popupTop + "px";
    },

    hide : function()
    {
        this.matches = new Array();
        this.selectedIndex = -1;
        this.scroll = 0;
        this.visible = false;
        this.popup.style.display = "none";
    },

    select : function()
    {
        if(this.selectedIndex != -1)
        {
            this.element.value = this.matches[this.selectedIndex];
        }

        this.hide();
    }
};

